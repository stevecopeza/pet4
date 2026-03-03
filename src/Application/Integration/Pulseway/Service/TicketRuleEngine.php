<?php

declare(strict_types=1);

namespace Pet\Application\Integration\Pulseway\Service;

use Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository;

/**
 * Evaluates ticket creation rules from wp_pet_pulseway_ticket_rules.
 *
 * Rules are evaluated in sort_order (ascending). First match wins.
 * A notification that matches no rule will not create a ticket.
 */
final class TicketRuleEngine
{
    private SqlPulsewayIntegrationRepository $repo;

    public function __construct(SqlPulsewayIntegrationRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Find the first matching rule for a notification.
     *
     * @param int   $integrationId
     * @param array $notification Row from wp_pet_external_notifications
     *
     * @return array|null The matched rule row, or null if no match
     */
    public function evaluate(int $integrationId, array $notification): ?array
    {
        $rules = $this->repo->findActiveRulesByIntegration($integrationId);

        foreach ($rules as $rule) {
            if ($this->matches($rule, $notification)) {
                return $rule;
            }
        }

        return null;
    }

    private function matches(array $rule, array $notification): bool
    {
        // Severity filter — comma-separated list allowed
        if (!empty($rule['match_severity'])) {
            $allowed = array_map('trim', explode(',', $rule['match_severity']));
            $severity = $notification['severity'] ?? '';
            if (!in_array($severity, $allowed, true)) {
                return false;
            }
        }

        // Category filter — comma-separated list allowed
        if (!empty($rule['match_category'])) {
            $allowed = array_map('trim', explode(',', $rule['match_category']));
            $category = $notification['category'] ?? '';
            if (!in_array($category, $allowed, true)) {
                return false;
            }
        }

        // Org ID filter
        if (!empty($rule['match_pulseway_org_id'])) {
            // Notification doesn't carry org_id directly — look it up via device's external_org_id
            // For now we skip org/site/group matching on the notification itself
            // This will be refined when device→notification linking is more mature
        }

        // Quiet hours check
        if (!empty($rule['quiet_hours_start']) && !empty($rule['quiet_hours_end'])) {
            $now = new \DateTimeImmutable();
            $currentTime = $now->format('H:i:s');
            $start = $rule['quiet_hours_start'];
            $end = $rule['quiet_hours_end'];

            if ($start <= $end) {
                // Same day window: e.g. 22:00 → 06:00 doesn't apply here
                if ($currentTime >= $start && $currentTime <= $end) {
                    return false;
                }
            } else {
                // Overnight window: e.g. 22:00 → 06:00
                if ($currentTime >= $start || $currentTime <= $end) {
                    return false;
                }
            }
        }

        return true;
    }
}
