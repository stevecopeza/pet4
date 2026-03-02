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
        $summary = [];
        $tables = [
            // children first (schema-aligned)
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
            $res = $this->purgeWithRegistry($t, $seedRunId);
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

        return $summary;
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

    private function purgeWithRegistry(string $table, string $seedRunId): ?array
    {
        $registry = $this->wpdb->prefix . 'pet_demo_seed_registry';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$registry'") !== $registry) {
            return null;
        }
        $ids = $this->wpdb->get_col($this->wpdb->prepare("SELECT row_id FROM $registry WHERE seed_run_id = %s AND table_name = %s", [$seedRunId, $table]));
        if (!$ids) {
            return ['deleted' => 0, 'archived' => 0];
        }
        $deleted = 0;
        $archived = 0;
        foreach ($ids as $id) {
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
        $cols = $this->wpdb->get_col("DESCRIBE $table", 0);
        return in_array($column, $cols, true);
        }

    private function countRows(string $table): int
    {
        return (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $table");
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
