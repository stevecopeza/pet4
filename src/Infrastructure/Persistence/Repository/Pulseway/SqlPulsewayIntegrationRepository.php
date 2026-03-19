<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository\Pulseway;

final class SqlPulsewayIntegrationRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    // ── Integrations ──

    public function findActiveIntegrations(): array
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        return $this->wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 AND archived_at IS NULL ORDER BY id ASC",
            ARRAY_A
        ) ?: [];
    }

    public function findIntegrationById(int $id): ?array
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findAllIntegrations(): array
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        return $this->wpdb->get_results(
            "SELECT * FROM $table WHERE archived_at IS NULL ORDER BY id ASC",
            ARRAY_A
        ) ?: [];
    }

    public function insertIntegration(array $data): int
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $this->wpdb->insert($table, $data);
        return (int) $this->wpdb->insert_id;
    }

    public function updateIntegration(int $id, array $data): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        $data['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->wpdb->update($table, $data, ['id' => $id]);
    }

    public function updatePollState(int $id, ?string $cursor, ?string $lastPollAt): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        $this->wpdb->update($table, [
            'last_poll_cursor' => $cursor,
            'last_poll_at' => $lastPollAt,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function recordSuccess(int $id): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->wpdb->update($table, [
            'last_success_at' => $now,
            'consecutive_failures' => 0,
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function recordFailure(int $id, string $errorMessage): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Increment consecutive_failures atomically
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $table SET consecutive_failures = consecutive_failures + 1, last_error_at = %s, last_error_message = %s, updated_at = %s WHERE id = %d",
            $now,
            substr($errorMessage, 0, 2000),
            $now,
            $id
        ));
    }

    // ── External Notifications ──

    /**
     * Insert a notification record idempotently using INSERT IGNORE.
     * Returns true if a new row was inserted, false if it was a duplicate.
     */
    public function insertNotificationIdempotent(array $data): bool
    {
        $table = $this->wpdb->prefix . 'pet_external_notifications';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data['received_at'] = $data['received_at'] ?? $now;
        $data['created_at'] = $now;

        $columns = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '%s'));

        $sql = "INSERT IGNORE INTO $table ($columns) VALUES ($placeholders)";
        $prepared = $this->wpdb->prepare($sql, array_values($data));
        $this->wpdb->query($prepared);

        return $this->wpdb->rows_affected > 0;
    }

    public function updateNotificationRoutingStatus(int $id, string $status): void
    {
        $table = $this->wpdb->prefix . 'pet_external_notifications';
        $this->wpdb->update($table, ['routing_status' => $status], ['id' => $id]);
    }

    public function findPendingNotifications(int $integrationId, int $limit = 100): array
    {
        $table = $this->wpdb->prefix . 'pet_external_notifications';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE integration_id = %d AND routing_status = 'pending' ORDER BY id ASC LIMIT %d",
            $integrationId,
            $limit
        ), ARRAY_A) ?: [];
    }

    public function findRecentNotifications(int $integrationId, int $limit = 50): array
    {
        $table = $this->wpdb->prefix . 'pet_external_notifications';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE integration_id = %d ORDER BY received_at DESC LIMIT %d",
            $integrationId,
            $limit
        ), ARRAY_A) ?: [];
    }

    public function countNotificationsByStatus(int $integrationId): array
    {
        $table = $this->wpdb->prefix . 'pet_external_notifications';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT routing_status, COUNT(*) as cnt FROM $table WHERE integration_id = %d GROUP BY routing_status",
            $integrationId
        ), ARRAY_A) ?: [];

        $counts = ['pending' => 0, 'routed' => 0, 'unroutable' => 0];
        foreach ($rows as $row) {
            $counts[$row['routing_status']] = (int) $row['cnt'];
        }
        return $counts;
    }

    // ── External Assets ──

    public function upsertAsset(array $data): void
    {
        $table = $this->wpdb->prefix . 'pet_external_assets';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $table WHERE integration_id = %d AND external_system = %s AND external_asset_id = %s",
            $data['integration_id'],
            $data['external_system'] ?? 'pulseway',
            $data['external_asset_id']
        ));

        if ($existing) {
            $data['updated_at'] = $now;
            $data['snapshot_updated_at'] = $now;
            $this->wpdb->update($table, $data, ['id' => (int) $existing]);
        } else {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $data['snapshot_updated_at'] = $now;
            $this->wpdb->insert($table, $data);
        }
    }

    public function findAssetsByIntegration(int $integrationId): array
    {
        $table = $this->wpdb->prefix . 'pet_external_assets';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE integration_id = %d AND archived_at IS NULL ORDER BY display_name ASC",
            $integrationId
        ), ARRAY_A) ?: [];
    }

    // ── Org Mappings ──

    public function findOrgMapping(int $integrationId, ?string $orgId, ?string $siteId, ?string $groupId): ?array
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_org_mappings';

        $eligibleClauses = [];
        $caseWhen = [];
        $eligibilityParams = [];
        $rankingParams = [];

        if ($orgId !== null && $siteId !== null && $groupId !== null) {
            $eligibleClauses[] = '(pulseway_org_id = %s AND pulseway_site_id = %s AND pulseway_group_id = %s)';
            $caseWhen[] = 'WHEN pulseway_org_id = %s AND pulseway_site_id = %s AND pulseway_group_id = %s THEN 1';
            array_push($eligibilityParams, $orgId, $siteId, $groupId);
            array_push($rankingParams, $orgId, $siteId, $groupId);
        }

        if ($orgId !== null && $siteId !== null) {
            $eligibleClauses[] = '(pulseway_org_id = %s AND pulseway_site_id = %s AND pulseway_group_id IS NULL)';
            $caseWhen[] = 'WHEN pulseway_org_id = %s AND pulseway_site_id = %s AND pulseway_group_id IS NULL THEN 2';
            array_push($eligibilityParams, $orgId, $siteId);
            array_push($rankingParams, $orgId, $siteId);
        }

        if ($orgId !== null) {
            $eligibleClauses[] = '(pulseway_org_id = %s AND pulseway_site_id IS NULL AND pulseway_group_id IS NULL)';
            $caseWhen[] = 'WHEN pulseway_org_id = %s AND pulseway_site_id IS NULL AND pulseway_group_id IS NULL THEN 3';
            array_push($eligibilityParams, $orgId);
            array_push($rankingParams, $orgId);
        }

        $eligibleClauses[] = '(pulseway_org_id IS NULL AND pulseway_site_id IS NULL AND pulseway_group_id IS NULL)';
        $caseWhen[] = 'WHEN pulseway_org_id IS NULL AND pulseway_site_id IS NULL AND pulseway_group_id IS NULL THEN 4';

        if (!$eligibleClauses) {
            return null;
        }

        $eligibility = implode(' OR ', $eligibleClauses);
        $ranking = implode(' ', $caseWhen);

        $params = array_merge([$integrationId], $eligibilityParams, $rankingParams);

        $sql = "
            SELECT *
            FROM $table
            WHERE integration_id = %d
              AND is_active = 1
              AND archived_at IS NULL
              AND ($eligibility)
            ORDER BY (CASE $ranking ELSE 99 END) ASC, id ASC
            LIMIT 1
        ";

        $row = $this->wpdb->get_row($this->wpdb->prepare($sql, $params), ARRAY_A);
        return $row ?: null;
    }

    public function findMappingsByIntegration(int $integrationId): array
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_org_mappings';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE integration_id = %d AND archived_at IS NULL ORDER BY id ASC",
            $integrationId
        ), ARRAY_A) ?: [];
    }

    public function insertOrgMapping(array $data): int
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_org_mappings';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $this->wpdb->insert($table, $data);
        return (int) $this->wpdb->insert_id;
    }

    public function updateOrgMapping(int $id, array $data): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_org_mappings';
        $data['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->wpdb->update($table, $data, ['id' => $id]);
    }

    // ── Ticket Rules ──

    public function findActiveRulesByIntegration(int $integrationId): array
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_ticket_rules';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE integration_id = %d AND is_active = 1 AND archived_at IS NULL ORDER BY sort_order ASC, id ASC",
            $integrationId
        ), ARRAY_A) ?: [];
    }

    public function findRulesByIntegration(int $integrationId): array
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_ticket_rules';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE integration_id = %d AND archived_at IS NULL ORDER BY sort_order ASC, id ASC",
            $integrationId
        ), ARRAY_A) ?: [];
    }

    public function insertRule(array $data): int
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_ticket_rules';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $this->wpdb->insert($table, $data);
        return (int) $this->wpdb->insert_id;
    }

    public function updateRule(int $id, array $data): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_ticket_rules';
        $data['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->wpdb->update($table, $data, ['id' => $id]);
    }
}
