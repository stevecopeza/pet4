<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

final class DemoPurgeService
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function purgeBySeedRunId(string $seedRunId): array
    {
        return $this->purgeBySeedRunIdInternal($seedRunId, true);
    }

    public function purgeBySeedRunIdForCleanBaseline(string $seedRunId): array
    {
        return $this->purgeBySeedRunIdInternal($seedRunId, false);
    }

    private function purgeBySeedRunIdInternal(string $seedRunId, bool $respectImmutableGuards): array
    {
        $summary = [];
        $tables = [
            // children first (schema-aligned)
            $this->wpdb->prefix . 'pet_person_skills',
            $this->wpdb->prefix . 'pet_person_certifications',
            $this->wpdb->prefix . 'pet_person_role_assignments',
            $this->wpdb->prefix . 'pet_person_kpis',
            $this->wpdb->prefix . 'pet_team_members',
            $this->wpdb->prefix . 'pet_conversation_events',
            $this->wpdb->prefix . 'pet_conversation_participants',
            $this->wpdb->prefix . 'pet_conversation_read_state',
            $this->wpdb->prefix . 'pet_decision_events',
            $this->wpdb->prefix . 'pet_decisions',
            $this->wpdb->prefix . 'pet_conversations',
            $this->wpdb->prefix . 'pet_project_tasks',
            $this->wpdb->prefix . 'pet_department_queues',
            $this->wpdb->prefix . 'pet_work_items',
            $this->wpdb->prefix . 'pet_sla_clock_state',
            $this->wpdb->prefix . 'pet_ticket_links',
            $this->wpdb->prefix . 'pet_tickets',
            $this->wpdb->prefix . 'pet_tasks',
            $this->wpdb->prefix . 'pet_projects',
            $this->wpdb->prefix . 'pet_baseline_components',
            $this->wpdb->prefix . 'pet_baselines',
            $this->wpdb->prefix . 'pet_contracts',
            $this->wpdb->prefix . 'pet_quote_components',
            $this->wpdb->prefix . 'pet_quote_milestones',
            $this->wpdb->prefix . 'pet_quote_catalog_items',
            $this->wpdb->prefix . 'pet_quote_recurring_services',
            $this->wpdb->prefix . 'pet_quotes',
            $this->wpdb->prefix . 'pet_billing_export_items',
            $this->wpdb->prefix . 'pet_billing_exports',
            $this->wpdb->prefix . 'pet_external_mappings',
            $this->wpdb->prefix . 'pet_integration_runs',
            $this->wpdb->prefix . 'pet_qb_payments',
            $this->wpdb->prefix . 'pet_qb_invoices',
            $this->wpdb->prefix . 'pet_feed_events',
            $this->wpdb->prefix . 'pet_announcements',
            $this->wpdb->prefix . 'pet_articles',
            $this->wpdb->prefix . 'pet_contacts',
            $this->wpdb->prefix . 'pet_sites',
            $this->wpdb->prefix . 'pet_customers',
            $this->wpdb->prefix . 'pet_employees',
            $this->wpdb->prefix . 'pet_time_entries',
        ];

        foreach ($tables as $t) {
            $summary[$t] = ['deleted' => 0, 'archived' => 0, 'skipped' => false];
            if (!$this->tableExists($t)) {
                $summary[$t]['skipped'] = true;
                continue;
            }
            if ($this->tableHasColumn($t, 'metadata_json')) {
                $res = $this->purgeWithMetadata($t, $seedRunId);
                $summary[$t]['deleted'] += $res['deleted'];
                $summary[$t]['archived'] += $res['archived'];
            }
            if ($this->tableHasColumn($t, 'malleable_data')) {
                $res = $this->purgeWithMalleable($t, $seedRunId);
                $summary[$t]['deleted'] += $res['deleted'];
                $summary[$t]['archived'] += $res['archived'];
            }
            $res = $this->purgeWithRegistry($t, $seedRunId, $respectImmutableGuards);
            if ($res !== null) {
                $summary[$t]['deleted'] += $res['deleted'];
                $summary[$t]['archived'] += $res['archived'];
            } else {
                if (!$this->tableHasColumn($t, 'metadata_json') && !$this->tableHasColumn($t, 'malleable_data')) {
                    $summary[$t]['skipped'] = true;
                }
            }
        }

        // Preserve immutable tables
        $summary['event_stream_preserved'] = $this->countRows($this->wpdb->prefix . 'pet_domain_event_stream');

        // Mark registry rows PURGED/ARCHIVED (do not delete)
        $registry = $this->wpdb->prefix . 'pet_demo_seed_registry';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$registry'") === $registry && $this->tableHasColumn($registry, 'purge_status')) {
            $summary['registry_marked'] = (int)$this->wpdb->query($this->wpdb->prepare("UPDATE $registry SET purge_status = 'PURGED', purged_at = NOW() WHERE seed_run_id = %s AND purge_status = 'ACTIVE'", [$seedRunId]));
        } else {
            $summary['registry_marked'] = 0;
        }
        if ($this->tableExists($registry)) {
            $summary['registry_deleted'] = (int)$this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM $registry WHERE seed_run_id = %s",
                [$seedRunId]
            ));
        } else {
            $summary['registry_deleted'] = 0;
        }

        return $summary;
    }

    /**
     * @return array<int, array{seed_run_id:string, registry_rows:int, first_seen_at:?string, last_seen_at:?string}>
     */
    public function listTrackedSeedRuns(bool $activeOnly = true): array
    {
        $registry = $this->wpdb->prefix . 'pet_demo_seed_registry';
        if (!$this->tableExists($registry)) {
            return [];
        }

        $where = [];
        if ($activeOnly && $this->tableHasColumn($registry, 'purge_status')) {
            $where[] = "purge_status = 'ACTIVE'";
        }
        $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

        $rows = $this->wpdb->get_results(
            "SELECT seed_run_id, COUNT(*) AS registry_rows, MIN(created_at) AS first_seen_at, MAX(created_at) AS last_seen_at
             FROM $registry
             $whereSql
             GROUP BY seed_run_id
             ORDER BY last_seen_at DESC, seed_run_id DESC",
            defined('ARRAY_A') ? ARRAY_A : 'ARRAY_A'
        );
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'seed_run_id' => (string)($row['seed_run_id'] ?? ''),
                'registry_rows' => (int)($row['registry_rows'] ?? 0),
                'first_seen_at' => isset($row['first_seen_at']) ? (string)$row['first_seen_at'] : null,
                'last_seen_at' => isset($row['last_seen_at']) ? (string)$row['last_seen_at'] : null,
            ];
        }, $rows);
    }

    /**
     * Purges all currently tracked ACTIVE runs in deterministic, newest-first order.
     */
    public function purgeAllActiveTrackedRuns(): array
    {
        $runs = $this->listTrackedSeedRuns(true);
        $runIds = array_values(array_filter(array_map(static function (array $run): string {
            return (string)($run['seed_run_id'] ?? '');
        }, $runs), static function (string $runId): bool {
            return $runId !== '';
        }));

        $purgedRuns = [];
        $failedRuns = [];
        $totals = [
            'deleted' => 0,
            'archived' => 0,
            'registry_deleted' => 0,
            'registry_marked' => 0,
        ];

        foreach ($runIds as $seedRunId) {
            try {
                $summary = $this->purgeBySeedRunIdForCleanBaseline($seedRunId);
                $runDeleted = 0;
                $runArchived = 0;
                foreach ($summary as $tableSummary) {
                    if (!is_array($tableSummary)) {
                        continue;
                    }
                    $runDeleted += (int)($tableSummary['deleted'] ?? 0);
                    $runArchived += (int)($tableSummary['archived'] ?? 0);
                }
                $registryDeleted = (int)($summary['registry_deleted'] ?? 0);
                $registryMarked = (int)($summary['registry_marked'] ?? 0);
                $totals['deleted'] += $runDeleted;
                $totals['archived'] += $runArchived;
                $totals['registry_deleted'] += $registryDeleted;
                $totals['registry_marked'] += $registryMarked;

                $remainingRows = $this->countRegistryRowsForRun($seedRunId);
                $purgedRuns[] = [
                    'seed_run_id' => $seedRunId,
                    'summary' => $summary,
                    'deleted' => $runDeleted,
                    'archived' => $runArchived,
                    'registry_deleted' => $registryDeleted,
                    'registry_marked' => $registryMarked,
                    'registry_rows_remaining' => $remainingRows,
                ];
            } catch (\Throwable $e) {
                $failedRuns[] = [
                    'seed_run_id' => $seedRunId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'active_runs_discovered' => count($runIds),
            'runs_considered' => $runs,
            'purged_runs' => $purgedRuns,
            'failed_runs' => $failedRuns,
            'totals' => $totals,
            'all_purges_succeeded' => empty($failedRuns),
        ];
    }

    private function purgeWithMetadata(string $table, string $seedRunId): array
    {
        // Delete untouched and non-immutable
        $deleteSql = "DELETE FROM $table WHERE JSON_EXTRACT(metadata_json, '$.seed_run_id') = %s AND (JSON_EXTRACT(metadata_json, '$.touched_at') IS NULL)";
        // Immutable set conditions per table
        if (str_ends_with($table, 'pet_quotes')) {
            $deleteSql .= " AND accepted_at IS NULL";
        }
        if (str_ends_with($table, 'pet_time_entries')) {
            $deleteSql .= " AND (status NOT IN ('submitted','approved') AND submitted_at IS NULL)";
        }
        if (str_ends_with($table, 'pet_billing_exports')) {
            $deleteSql .= " AND status NOT IN ('queued','sent','confirmed')";
        }
        $deleted = $this->wpdb->query($this->wpdb->prepare($deleteSql, [$seedRunId]));

        // Archive touched where archived_at exists
        $archived = 0;
        if ($this->tableHasColumn($table, 'archived_at')) {
            $archivedSql = "UPDATE $table SET archived_at = NOW() WHERE JSON_EXTRACT(metadata_json, '$.seed_run_id') = %s AND JSON_EXTRACT(metadata_json, '$.touched_at') IS NOT NULL";
            $archived = $this->wpdb->query($this->wpdb->prepare($archivedSql, [$seedRunId]));
        }

        return ['deleted' => (int)$deleted, 'archived' => (int)$archived];
    }

    private function purgeWithMalleable(string $table, string $seedRunId): array
    {
        $deleteSql = "DELETE FROM $table WHERE JSON_EXTRACT(malleable_data, '$.seed_run_id') = %s AND (JSON_EXTRACT(malleable_data, '$.touched_at') IS NULL)";
        if (str_ends_with($table, 'pet_quotes')) {
            $deleteSql .= " AND accepted_at IS NULL";
        }
        if (str_ends_with($table, 'pet_time_entries')) {
            $deleteSql .= " AND (status NOT IN ('submitted','approved') AND submitted_at IS NULL)";
        }
        if (str_ends_with($table, 'pet_billing_exports')) {
            $deleteSql .= " AND status NOT IN ('queued','sent','confirmed')";
        }
        $deleted = $this->wpdb->query($this->wpdb->prepare($deleteSql, [$seedRunId]));
        $archived = 0;
        if ($this->tableHasColumn($table, 'archived_at')) {
            $archivedSql = "UPDATE $table SET archived_at = NOW() WHERE JSON_EXTRACT(malleable_data, '$.seed_run_id') = %s AND JSON_EXTRACT(malleable_data, '$.touched_at') IS NOT NULL";
            $archived = $this->wpdb->query($this->wpdb->prepare($archivedSql, [$seedRunId]));
        }
        return ['deleted' => (int)$deleted, 'archived' => (int)$archived];
    }

    private function purgeWithRegistry(string $table, string $seedRunId, bool $respectImmutableGuards = true): ?array
    {
        if (!$this->tableExists($table)) {
            return ['deleted' => 0, 'archived' => 0];
        }
        $registry = $this->wpdb->prefix . 'pet_demo_seed_registry';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$registry'") !== $registry) {
            return null;
        }
        $hasPurgeStatus = $this->tableHasColumn($registry, 'purge_status');
        if ($hasPurgeStatus) {
            $ids = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT row_id FROM $registry WHERE seed_run_id = %s AND table_name = %s AND purge_status = 'ACTIVE'",
                [$seedRunId, $table]
            ));
        } else {
            $ids = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT row_id FROM $registry WHERE seed_run_id = %s AND table_name = %s",
                [$seedRunId, $table]
            ));
        }
        if (!$ids) {
            return ['deleted' => 0, 'archived' => 0];
        }
        $deleted = 0;
        $archived = 0;
        foreach ($ids as $id) {
            if ($respectImmutableGuards) {
                // Immutable guards
                if (str_ends_with($table, 'pet_quotes')) {
                    $accepted = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id = %d AND accepted_at IS NOT NULL", [(int)$id]));
                    if ($accepted > 0) continue;
                }
                if (str_ends_with($table, 'pet_time_entries')) {
                    $isImmutable = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id = %d AND (status IN ('submitted','approved') OR submitted_at IS NOT NULL)", [(int)$id]));
                    if ($isImmutable > 0) continue;
                }
                if (str_ends_with($table, 'pet_billing_exports')) {
                    $isQueued = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id = %d AND status IN ('queued','sent','confirmed')", [(int)$id]));
                    if ($isQueued > 0) continue;
                }
            }
            // Archive when supported and touched flag present in JSON columns (best-effort)
            if ($this->tableHasColumn($table, 'archived_at') && $this->tableHasColumn($table, 'metadata_json')) {
                $touched = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id = %s AND JSON_EXTRACT(metadata_json, '$.touched_at') IS NOT NULL", [$id]));
                if ($touched > 0) {
                    $archived += (int)$this->wpdb->query($this->wpdb->prepare("UPDATE $table SET archived_at = NOW() WHERE id = %s", [$id]));
                    $this->markRegistry($seedRunId, $table, (string)$id, 'ARCHIVED');
                    continue;
                }
            }
            $deleted += (int)$this->wpdb->query($this->wpdb->prepare("DELETE FROM $table WHERE id = %s", [$id]));
            $this->markRegistry($seedRunId, $table, (string)$id, 'PURGED');
        }
        return ['deleted' => $deleted, 'archived' => $archived];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        $cols = $this->wpdb->get_col("DESCRIBE $table", 0);
        return in_array($column, $cols, true);
    }
    private function tableExists(string $table): bool
    {
        return $this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }

    private function countRows(string $table): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    private function countRegistryRowsForRun(string $seedRunId): int
    {
        $registry = $this->wpdb->prefix . 'pet_demo_seed_registry';
        if (!$this->tableExists($registry)) {
            return 0;
        }

        return (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $registry WHERE seed_run_id = %s",
            [$seedRunId]
        ));
    }

    private function markRegistry(string $seedRunId, string $table, string $rowId, string $status): void
    {
        $registry = $this->wpdb->prefix . 'pet_demo_seed_registry';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$registry'") !== $registry) {
            return;
        }
        $cols = $this->wpdb->get_col("DESCRIBE $registry", 0);
        if (!in_array('purge_status', $cols, true)) {
            return;
        }
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $registry SET purge_status = %s, purged_at = NOW() WHERE seed_run_id = %s AND table_name = %s AND row_id = %s",
            [$status, $seedRunId, $table, $rowId]
        ));
    }
}
