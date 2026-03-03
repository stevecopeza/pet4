<?php

declare(strict_types=1);

namespace Pet\Application\Integration\Pulseway\Service;

use Pet\Infrastructure\Integration\Pulseway\PulsewayApiClient;
use Pet\Infrastructure\Integration\Pulseway\PulsewayApiException;
use Pet\Infrastructure\Integration\Pulseway\CredentialEncryptionService;
use Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository;
use Pet\Application\System\Service\FeatureFlagService;

/**
 * Syncs Pulseway device inventory into wp_pet_external_assets.
 * Designed for lower-frequency execution (e.g. every 15 minutes via system cron).
 */
final class DeviceSnapshotService
{
    private const DEFAULT_PAGE_SIZE = 50;

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
     * Sync devices for all active integrations.
     */
    public function syncAll(): array
    {
        if (!$this->flags->isPulsewayEnabled()) {
            return ['skipped' => 'pulseway_disabled'];
        }

        $integrations = $this->repo->findActiveIntegrations();
        $results = [];

        foreach ($integrations as $integration) {
            $id = (int) $integration['id'];
            $results[$id] = $this->syncIntegration($integration);
        }

        return $results;
    }

    /**
     * Sync devices for a single integration.
     */
    public function syncIntegrationById(int $integrationId): array
    {
        if (!$this->flags->isPulsewayEnabled()) {
            return ['skipped' => 'pulseway_disabled'];
        }

        $integration = $this->repo->findIntegrationById($integrationId);
        if (!$integration) {
            return ['error' => 'integration_not_found'];
        }

        return $this->syncIntegration($integration);
    }

    private function syncIntegration(array $integration): array
    {
        $id = (int) $integration['id'];

        try {
            $tokenId = $this->encryption->decrypt($integration['token_id_encrypted']);
            $tokenSecret = $this->encryption->decrypt($integration['token_secret_encrypted']);
            $baseUrl = $integration['api_base_url'] ?? 'https://api.pulseway.com/v3';
            $client = new PulsewayApiClient($baseUrl, $tokenId, $tokenSecret);

            $upserted = 0;
            $skip = 0;

            // Paginate through all devices
            do {
                $response = $client->getDevices(self::DEFAULT_PAGE_SIZE, $skip);
                $devices = $response['data'] ?? [];

                if (!is_array($devices) || empty($devices)) {
                    break;
                }

                foreach ($devices as $device) {
                    $row = $this->mapDeviceToRow($id, $device);
                    $this->repo->upsertAsset($row);
                    $upserted++;
                }

                $skip += count($devices);
            } while (count($devices) >= self::DEFAULT_PAGE_SIZE);

            $this->repo->recordSuccess($id);

            return [
                'status' => 'ok',
                'devices_synced' => $upserted,
            ];
        } catch (PulsewayApiException $e) {
            $this->repo->recordFailure($id, $e->getMessage());
            return [
                'status' => 'api_error',
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            $this->repo->recordFailure($id, $e->getMessage());
            error_log('[PET Pulseway] Device sync failed for integration ' . $id . ': ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function mapDeviceToRow(int $integrationId, array $device): array
    {
        $deviceId = $device['Id'] ?? $device['id'] ?? '';
        $name = $device['Name'] ?? $device['name'] ?? $device['ComputerName'] ?? '';
        $platform = $device['OsType'] ?? $device['Platform'] ?? $device['platform'] ?? null;
        $status = $device['Status'] ?? $device['status'] ?? null;
        $lastSeen = $device['LastOnline'] ?? $device['lastOnline'] ?? null;
        $orgId = $device['OrganizationId'] ?? $device['organizationId'] ?? null;
        $siteId = $device['SiteId'] ?? $device['siteId'] ?? null;
        $groupId = $device['GroupId'] ?? $device['groupId'] ?? null;

        // Parse last-seen date
        $parsedLastSeen = null;
        if ($lastSeen) {
            try {
                $dt = new \DateTimeImmutable($lastSeen);
                $parsedLastSeen = $dt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // Leave null
            }
        }

        return [
            'integration_id' => $integrationId,
            'external_system' => 'pulseway',
            'external_asset_id' => (string) $deviceId,
            'external_org_id' => $orgId !== null ? (string) $orgId : null,
            'external_site_id' => $siteId !== null ? (string) $siteId : null,
            'external_group_id' => $groupId !== null ? (string) $groupId : null,
            'display_name' => substr($name, 0, 255),
            'platform' => $platform !== null ? substr((string) $platform, 0, 64) : null,
            'status' => $status !== null ? substr((string) $status, 0, 32) : null,
            'last_seen_at' => $parsedLastSeen,
            'raw_snapshot_json' => json_encode($device, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }
}
