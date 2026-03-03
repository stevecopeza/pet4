<?php

declare(strict_types=1);

namespace Pet\Application\Integration\Pulseway\Service;

use Pet\Infrastructure\Integration\Pulseway\PulsewayApiClient;
use Pet\Infrastructure\Integration\Pulseway\PulsewayApiException;
use Pet\Infrastructure\Integration\Pulseway\PulsewayRateLimitException;
use Pet\Infrastructure\Integration\Pulseway\CredentialEncryptionService;
use Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository;
use Pet\Application\System\Service\FeatureFlagService;

/**
 * Polls Pulseway for new notifications and ingests them into the
 * wp_pet_external_notifications table. Implements the contract §6.3 poll cycle:
 *
 *   read cursor → fetch → compute dedupe_key → INSERT IGNORE → update cursor
 *
 * Each ingested notification gets routing_status = 'pending'.
 * Circuit breaker halts polling after 6 consecutive failures.
 */
final class NotificationIngestionService
{
    private const CIRCUIT_BREAKER_THRESHOLD = 6;
    private const DEFAULT_BATCH_SIZE = 100;

    private SqlPulsewayIntegrationRepository $repo;
    private CredentialEncryptionService $encryption;
    private FeatureFlagService $flags;

    public function __construct(
        SqlPulsewayIntegrationRepository $repo,
        CredentialEncryptionService $encryption,
        FeatureFlagService $flags
    ) {
        $this->repo = $repo;
        $this->encryption = $encryption;
        $this->flags = $flags;
    }

    /**
     * Run a single poll cycle for all active integrations.
     *
     * @return array<string, mixed> Summary per integration keyed by integration ID.
     */
    public function pollAll(): array
    {
        if (!$this->flags->isPulsewayEnabled()) {
            return ['skipped' => 'pulseway_disabled'];
        }

        $integrations = $this->repo->findActiveIntegrations();
        $results = [];

        foreach ($integrations as $integration) {
            $id = (int) $integration['id'];
            $results[$id] = $this->pollIntegration($integration);
        }

        return $results;
    }

    /**
     * Poll a single integration by ID.
     */
    public function pollIntegrationById(int $integrationId): array
    {
        if (!$this->flags->isPulsewayEnabled()) {
            return ['skipped' => 'pulseway_disabled'];
        }

        $integration = $this->repo->findIntegrationById($integrationId);
        if (!$integration) {
            return ['error' => 'integration_not_found'];
        }

        return $this->pollIntegration($integration);
    }

    private function pollIntegration(array $integration): array
    {
        $id = (int) $integration['id'];
        $consecutiveFailures = (int) ($integration['consecutive_failures'] ?? 0);

        // Circuit breaker
        if ($consecutiveFailures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            return [
                'status' => 'circuit_open',
                'consecutive_failures' => $consecutiveFailures,
                'message' => 'Circuit breaker open — manual reset required',
            ];
        }

        try {
            $client = $this->buildClient($integration);
            $result = $this->fetchAndIngest($client, $integration);

            // Record success
            $this->repo->recordSuccess($id);

            // Update poll cursor and timestamp
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->repo->updatePollState($id, $result['new_cursor'] ?? null, $now);

            return [
                'status' => 'ok',
                'ingested' => $result['ingested'],
                'duplicates' => $result['duplicates'],
                'total_fetched' => $result['total_fetched'],
            ];
        } catch (PulsewayRateLimitException $e) {
            $this->repo->recordFailure($id, $e->getMessage());
            return [
                'status' => 'rate_limited',
                'message' => $e->getMessage(),
            ];
        } catch (PulsewayApiException $e) {
            $this->repo->recordFailure($id, $e->getMessage());
            return [
                'status' => 'api_error',
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            $this->repo->recordFailure($id, $e->getMessage());
            error_log('[PET Pulseway] Poll failed for integration ' . $id . ': ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function fetchAndIngest(PulsewayApiClient $client, array $integration): array
    {
        $integrationId = (int) $integration['id'];
        $cursor = $integration['last_poll_cursor'] ?? null;
        $batchSize = self::DEFAULT_BATCH_SIZE;

        // Fetch notifications from Pulseway — skip-based pagination
        $skip = $cursor !== null ? (int) $cursor : 0;
        $response = $client->getNotifications($batchSize, $skip);
        $notifications = $response['data'] ?? [];

        if (!is_array($notifications)) {
            $notifications = [];
        }

        $ingested = 0;
        $duplicates = 0;
        $lastProcessedOffset = $skip;

        foreach ($notifications as $notification) {
            $dedupeKey = $this->computeDedupeKey($notification);
            $row = $this->mapNotificationToRow($integrationId, $notification, $dedupeKey);
            $wasNew = $this->repo->insertNotificationIdempotent($row);

            if ($wasNew) {
                $ingested++;
            } else {
                $duplicates++;
            }

            $lastProcessedOffset++;
        }

        // Advance cursor only if we got results
        $newCursor = count($notifications) > 0 ? (string) $lastProcessedOffset : $cursor;

        return [
            'ingested' => $ingested,
            'duplicates' => $duplicates,
            'total_fetched' => count($notifications),
            'new_cursor' => $newCursor,
        ];
    }

    /**
     * Compute a stable dedupe key from the notification.
     * Uses the Pulseway notification ID if available, otherwise hashes key fields.
     */
    private function computeDedupeKey(array $notification): string
    {
        // Pulseway notification IDs vary in format — prefer the explicit ID
        $externalId = $notification['Id'] ?? $notification['id'] ?? null;
        if ($externalId !== null && $externalId !== '') {
            return 'pulseway_' . (string) $externalId;
        }

        // Fallback: hash composite of device + title + timestamp
        $composite = implode('|', [
            $notification['DeviceId'] ?? $notification['deviceId'] ?? '',
            $notification['Title'] ?? $notification['title'] ?? '',
            $notification['Date'] ?? $notification['date'] ?? '',
        ]);
        return 'pulseway_hash_' . hash('sha256', $composite);
    }

    private function mapNotificationToRow(int $integrationId, array $notification, string $dedupeKey): array
    {
        // Normalise keys — Pulseway v3 uses PascalCase
        $externalId = $notification['Id'] ?? $notification['id'] ?? null;
        $deviceId = $notification['DeviceId'] ?? $notification['deviceId'] ?? null;
        $severity = $notification['Priority'] ?? $notification['Severity'] ?? $notification['severity'] ?? null;
        $category = $notification['Category'] ?? $notification['category'] ?? null;
        $title = $notification['Title'] ?? $notification['title'] ?? '(no title)';
        $message = $notification['Message'] ?? $notification['message'] ?? null;
        $occurredAt = $notification['Date'] ?? $notification['date'] ?? null;

        // Parse Pulseway date format
        $parsedOccurredAt = null;
        if ($occurredAt) {
            try {
                $dt = new \DateTimeImmutable($occurredAt);
                $parsedOccurredAt = $dt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // Leave null if unparseable
            }
        }

        return [
            'integration_id' => $integrationId,
            'external_system' => 'pulseway',
            'external_notification_id' => $externalId !== null ? (string) $externalId : null,
            'dedupe_key' => $dedupeKey,
            'device_external_id' => $deviceId !== null ? (string) $deviceId : null,
            'severity' => $severity !== null ? substr((string) $severity, 0, 32) : null,
            'category' => $category !== null ? substr((string) $category, 0, 64) : null,
            'title' => substr($title, 0, 512),
            'message' => $message,
            'occurred_at' => $parsedOccurredAt,
            'raw_payload_json' => json_encode($notification, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'routing_status' => 'pending',
        ];
    }

    private function buildClient(array $integration): PulsewayApiClient
    {
        $tokenId = $this->encryption->decrypt($integration['token_id_encrypted']);
        $tokenSecret = $this->encryption->decrypt($integration['token_secret_encrypted']);
        $baseUrl = $integration['api_base_url'] ?? 'https://api.pulseway.com/v3';

        return new PulsewayApiClient($baseUrl, $tokenId, $tokenSecret);
    }
}
