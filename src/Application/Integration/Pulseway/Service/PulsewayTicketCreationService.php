<?php

declare(strict_types=1);

namespace Pet\Application\Integration\Pulseway\Service;

use Pet\Application\Support\Command\CreateTicketCommand;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository;

/**
 * Creates PET tickets from Pulseway notifications.
 *
 * Flow per notification:
 *   1. Check dedupe — has this dedupe_key already been linked to a ticket?
 *   2. Evaluate rules — find matching rule
 *   3. Resolve customer — via org mapping
 *   4. Create ticket — via existing CreateTicketCommand
 *   5. Insert ticket_link — link_type='external', linked_id=dedupe_key
 *   6. Update routing_status → 'routed'
 */
final class PulsewayTicketCreationService
{
    private TicketRuleEngine $ruleEngine;
    private CreateTicketHandler $createTicketHandler;
    private SqlPulsewayIntegrationRepository $repo;
    private FeatureFlagService $flags;
    private \wpdb $wpdb;

    public function __construct(
        TicketRuleEngine $ruleEngine,
        CreateTicketHandler $createTicketHandler,
        SqlPulsewayIntegrationRepository $repo,
        FeatureFlagService $flags,
        \wpdb $wpdb
    ) {
        $this->ruleEngine = $ruleEngine;
        $this->createTicketHandler = $createTicketHandler;
        $this->repo = $repo;
        $this->flags = $flags;
        $this->wpdb = $wpdb;
    }

    /**
     * Process all pending notifications for an integration.
     *
     * @return array Summary: created, deduped, unroutable, errors
     */
    public function processPendingNotifications(int $integrationId): array
    {
        if (!$this->flags->isPulsewayEnabled() || !$this->flags->isPulsewayTicketCreationEnabled()) {
            return ['skipped' => 'ticket_creation_disabled'];
        }

        $notifications = $this->repo->findPendingNotifications($integrationId, 100);

        $created = 0;
        $deduped = 0;
        $unroutable = 0;
        $errors = 0;

        foreach ($notifications as $notification) {
            $result = $this->processNotification($integrationId, $notification);

            switch ($result) {
                case 'created':
                    $created++;
                    break;
                case 'deduped':
                    $deduped++;
                    break;
                case 'unroutable':
                    $unroutable++;
                    break;
                case 'error':
                    $errors++;
                    break;
            }
        }

        return [
            'processed' => count($notifications),
            'created' => $created,
            'deduped' => $deduped,
            'unroutable' => $unroutable,
            'errors' => $errors,
        ];
    }

    /**
     * Process a single notification.
     *
     * @return string One of: 'created', 'deduped', 'unroutable', 'error'
     */
    private function processNotification(int $integrationId, array $notification): string
    {
        $notificationId = (int) $notification['id'];
        $dedupeKey = $notification['dedupe_key'];

        // 1. Dedupe check — has this already been linked to a ticket?
        if ($this->isAlreadyLinked($dedupeKey)) {
            $this->repo->updateNotificationRoutingStatus($notificationId, 'routed');
            return 'deduped';
        }

        // 2. Evaluate rules
        $rule = $this->ruleEngine->evaluate($integrationId, $notification);
        if ($rule === null) {
            $this->repo->updateNotificationRoutingStatus($notificationId, 'unroutable');
            return 'unroutable';
        }

        // 3. Resolve customer via org mapping
        $customerId = $this->resolveCustomerId($integrationId, $notification);
        if ($customerId === null) {
            // No customer mapping — can't create a ticket without a customer
            $this->repo->updateNotificationRoutingStatus($notificationId, 'unroutable');
            return 'unroutable';
        }

        // 4. Resolve site
        $siteId = $this->resolveSiteId($integrationId, $notification);

        // 5. Build and execute CreateTicketCommand
        try {
            $subject = $this->buildSubject($notification);
            $description = $this->buildDescription($notification);
            $priority = $rule['output_priority'] ?? 'medium';

            $malleableData = [
                'intake_source' => 'pulseway',
                'queue_id' => $rule['output_queue_id'] ?? null,
                'owner_user_id' => $rule['output_owner_user_id'] ?? null,
                'category' => $notification['category'] ?? null,
                'pulseway_notification_id' => $notification['external_notification_id'] ?? null,
                'pulseway_dedupe_key' => $dedupeKey,
                'pulseway_severity' => $notification['severity'] ?? null,
                'pulseway_device_id' => $notification['device_external_id'] ?? null,
                'billing_context_type' => $rule['output_billing_context_type'] ?? 'adhoc',
            ];

            // Remove null values to keep malleable_data clean
            $malleableData = array_filter($malleableData, fn($v) => $v !== null);

            $command = new CreateTicketCommand(
                $customerId,
                $siteId,
                null, // slaId — will be resolved by handler if applicable
                $subject,
                $description,
                $priority,
                $malleableData
            );

            $this->createTicketHandler->handle($command);

            // 6. Find the ticket that was just created (most recent for this customer)
            $ticketId = $this->findMostRecentTicketId($customerId, $subject);

            // 7. Insert ticket_link for dedupe tracking
            if ($ticketId) {
                $this->insertTicketLink($ticketId, $dedupeKey);
            }

            // 8. Update routing_status
            $this->repo->updateNotificationRoutingStatus($notificationId, 'routed');

            return 'created';
        } catch (\Throwable $e) {
            error_log('[PET Pulseway] Ticket creation failed for notification ' . $notificationId . ': ' . $e->getMessage());
            // Leave as 'pending' for retry
            return 'error';
        }
    }

    private function isAlreadyLinked(string $dedupeKey): bool
    {
        $table = $this->wpdb->prefix . 'pet_ticket_links';
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE link_type = 'external' AND linked_id = %s",
            $dedupeKey
        ));
        return (int) $count > 0;
    }

    private function resolveCustomerId(int $integrationId, array $notification): ?int
    {
        // Try to find a mapping based on the device's org/site/group
        // First, look up the device to get org/site/group IDs
        $deviceExternalId = $notification['device_external_id'] ?? null;
        if ($deviceExternalId) {
            $assetsTable = $this->wpdb->prefix . 'pet_external_assets';
            $device = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT external_org_id, external_site_id, external_group_id FROM $assetsTable WHERE integration_id = %d AND external_asset_id = %s LIMIT 1",
                $integrationId,
                $deviceExternalId
            ), ARRAY_A);

            if ($device) {
                $mapping = $this->repo->findOrgMapping(
                    $integrationId,
                    $device['external_org_id'] ?? null,
                    $device['external_site_id'] ?? null,
                    $device['external_group_id'] ?? null
                );
                if ($mapping && !empty($mapping['pet_customer_id'])) {
                    return (int) $mapping['pet_customer_id'];
                }
            }
        }

        // Fallback: find a catch-all mapping (all NULLs) for this integration
        $mapping = $this->repo->findOrgMapping($integrationId, null, null, null);
        if ($mapping && !empty($mapping['pet_customer_id'])) {
            return (int) $mapping['pet_customer_id'];
        }

        return null;
    }

    private function resolveSiteId(int $integrationId, array $notification): ?int
    {
        $deviceExternalId = $notification['device_external_id'] ?? null;
        if ($deviceExternalId) {
            $assetsTable = $this->wpdb->prefix . 'pet_external_assets';
            $device = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT external_org_id, external_site_id, external_group_id FROM $assetsTable WHERE integration_id = %d AND external_asset_id = %s LIMIT 1",
                $integrationId,
                $deviceExternalId
            ), ARRAY_A);

            if ($device) {
                $mapping = $this->repo->findOrgMapping(
                    $integrationId,
                    $device['external_org_id'] ?? null,
                    $device['external_site_id'] ?? null,
                    $device['external_group_id'] ?? null
                );
                if ($mapping && !empty($mapping['pet_site_id'])) {
                    return (int) $mapping['pet_site_id'];
                }
            }
        }

        // Fallback catch-all
        $mapping = $this->repo->findOrgMapping($integrationId, null, null, null);
        if ($mapping && !empty($mapping['pet_site_id'])) {
            return (int) $mapping['pet_site_id'];
        }

        return null;
    }

    private function buildSubject(array $notification): string
    {
        $severity = $notification['severity'] ?? '';
        $title = $notification['title'] ?? '(no title)';

        if ($severity) {
            return "[Pulseway {$severity}] {$title}";
        }

        return "[Pulseway] {$title}";
    }

    private function buildDescription(array $notification): string
    {
        $parts = [];
        $parts[] = $notification['message'] ?? $notification['title'] ?? '';

        if (!empty($notification['device_external_id'])) {
            $parts[] = "\n\nDevice ID: " . $notification['device_external_id'];
        }
        if (!empty($notification['occurred_at'])) {
            $parts[] = "Occurred: " . $notification['occurred_at'];
        }
        if (!empty($notification['category'])) {
            $parts[] = "Category: " . $notification['category'];
        }

        $parts[] = "\n\n— Auto-created from Pulseway notification (dedupe: " . ($notification['dedupe_key'] ?? 'unknown') . ")";

        return implode("\n", array_filter($parts));
    }

    private function findMostRecentTicketId(int $customerId, string $subject): ?int
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $table WHERE customer_id = %d AND subject = %s ORDER BY id DESC LIMIT 1",
            $customerId,
            $subject
        ));
        return $id !== null ? (int) $id : null;
    }

    private function insertTicketLink(int $ticketId, string $dedupeKey): void
    {
        $table = $this->wpdb->prefix . 'pet_ticket_links';
        $this->wpdb->insert($table, [
            'ticket_id' => $ticketId,
            'link_type' => 'external',
            'linked_id' => $dedupeKey,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
