<?php

declare(strict_types=1);

namespace Pet\Application\Support\Query;

class SupportTeamOperationalSummaryQuery
{
    private $wpdb;
    private string $workItemsTable;
    private string $escalationsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
        $this->escalationsTable = $wpdb->prefix . 'pet_escalations';
    }

    public function getTeamSummary(int $teamId, array $teamWpUserIds): array
    {
        $teamIdStr = (string)$teamId;
        $teamWpUserIds = array_values(array_filter(array_map('strval', $teamWpUserIds), fn($v) => $v !== '' && $v !== '0'));

        $activeStatusSql = "status IN ('active','waiting')";

        $teamQueue = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->workItemsTable}
             WHERE source_type = %s
               AND $activeStatusSql
               AND assigned_team_id = %s
               AND (assigned_user_id IS NULL OR assigned_user_id = '')",
            'ticket',
            $teamIdStr
        ));

        $userAssigned = 0;
        if (!empty($teamWpUserIds)) {
            $placeholders = implode(',', array_fill(0, count($teamWpUserIds), '%s'));
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$this->workItemsTable}
                 WHERE source_type = %s
                   AND $activeStatusSql
                   AND assigned_user_id IN ($placeholders)",
                'ticket',
                ...$teamWpUserIds
            );
            $userAssigned = (int)$this->wpdb->get_var($sql);
        }

        $unrouted = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->workItemsTable}
             WHERE source_type = %s
               AND $activeStatusSql
               AND (assigned_team_id IS NULL OR assigned_team_id = '')
               AND (assigned_user_id IS NULL OR assigned_user_id = '')",
            'ticket'
        ));

        $slaBreached = $this->countSlaByPredicate($teamIdStr, $teamWpUserIds, "sla_time_remaining_minutes IS NOT NULL AND sla_time_remaining_minutes < 0");
        $slaRisk = $this->countSlaByPredicate($teamIdStr, $teamWpUserIds, "sla_time_remaining_minutes IS NOT NULL AND sla_time_remaining_minutes >= 0 AND sla_time_remaining_minutes < 60");

        $aging = $this->agingBuckets($teamIdStr, $teamWpUserIds);
        $workload = $this->workloadPerTechnician($teamWpUserIds);
        $escalations = $this->openEscalations($teamIdStr, $teamWpUserIds);

        return [
            'team_id' => $teamId,
            'counts' => [
                'team_queue' => $teamQueue,
                'user_assigned' => $userAssigned,
                'unrouted' => $unrouted,
                'total' => $teamQueue + $userAssigned + $unrouted,
            ],
            'sla' => [
                'breached' => $slaBreached,
                'risk' => $slaRisk,
            ],
            'aging' => $aging,
            'workload_per_technician' => $workload,
            'unresolved_escalations' => $escalations,
        ];
    }

    private function countSlaByPredicate(string $teamIdStr, array $teamWpUserIds, string $predicateSql): int
    {
        $activeStatusSql = "status IN ('active','waiting')";

        $clauses = [];
        $params = ['ticket'];

        $clauses[] = "(assigned_team_id = %s AND (assigned_user_id IS NULL OR assigned_user_id = ''))";
        $params[] = $teamIdStr;

        if (!empty($teamWpUserIds)) {
            $placeholders = implode(',', array_fill(0, count($teamWpUserIds), '%s'));
            $clauses[] = "assigned_user_id IN ($placeholders)";
            foreach ($teamWpUserIds as $id) {
                $params[] = $id;
            }
        }

        $whereScope = implode(' OR ', $clauses);

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->workItemsTable}
             WHERE source_type = %s
               AND $activeStatusSql
               AND ($whereScope)
               AND $predicateSql",
            ...$params
        );
        return (int)$this->wpdb->get_var($sql);
    }

    private function agingBuckets(string $teamIdStr, array $teamWpUserIds): array
    {
        $activeStatusSql = "status IN ('active','waiting')";
        $clauses = [];
        $params = ['ticket'];

        $clauses[] = "(assigned_team_id = %s AND (assigned_user_id IS NULL OR assigned_user_id = ''))";
        $params[] = $teamIdStr;

        if (!empty($teamWpUserIds)) {
            $placeholders = implode(',', array_fill(0, count($teamWpUserIds), '%s'));
            $clauses[] = "assigned_user_id IN ($placeholders)";
            foreach ($teamWpUserIds as $id) {
                $params[] = $id;
            }
        }

        $whereScope = implode(' OR ', $clauses);
        $sql = $this->wpdb->prepare(
            "SELECT
                SUM(CASE WHEN created_at >= datetime('now','-2 days') THEN 1 ELSE 0 END) AS lt2,
                SUM(CASE WHEN created_at < datetime('now','-2 days') AND created_at >= datetime('now','-7 days') THEN 1 ELSE 0 END) AS d2_7,
                SUM(CASE WHEN created_at < datetime('now','-7 days') AND created_at >= datetime('now','-14 days') THEN 1 ELSE 0 END) AS d7_14,
                SUM(CASE WHEN created_at < datetime('now','-14 days') THEN 1 ELSE 0 END) AS gt14
             FROM {$this->workItemsTable}
             WHERE source_type = %s
               AND $activeStatusSql
               AND ($whereScope)",
            ...$params
        );
        $row = $this->wpdb->get_row($sql);
        return [
            'lt_2_days' => $row ? (int)$row->lt2 : 0,
            'days_2_to_7' => $row ? (int)$row->d2_7 : 0,
            'days_7_to_14' => $row ? (int)$row->d7_14 : 0,
            'gt_14_days' => $row ? (int)$row->gt14 : 0,
        ];
    }

    private function workloadPerTechnician(array $teamWpUserIds): array
    {
        if (empty($teamWpUserIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($teamWpUserIds), '%s'));
        $sql = $this->wpdb->prepare(
            "SELECT assigned_user_id, COUNT(*) AS c
             FROM {$this->workItemsTable}
             WHERE source_type = %s
               AND status IN ('active','waiting')
               AND assigned_user_id IN ($placeholders)
             GROUP BY assigned_user_id
             ORDER BY c DESC",
            'ticket',
            ...$teamWpUserIds
        );
        $rows = $this->wpdb->get_results($sql);
        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[] = ['wp_user_id' => (string)$r->assigned_user_id, 'count' => (int)$r->c];
        }
        return $out;
    }

    private function openEscalations(string $teamIdStr, array $teamWpUserIds): array
    {
        $activeStatusSql = "wi.status IN ('active','waiting')";
        $clauses = [];
        $params = ['ticket', 'OPEN', 'ticket'];

        $clauses[] = "(wi.assigned_team_id = %s AND (wi.assigned_user_id IS NULL OR wi.assigned_user_id = ''))";
        $params[] = $teamIdStr;

        if (!empty($teamWpUserIds)) {
            $placeholders = implode(',', array_fill(0, count($teamWpUserIds), '%s'));
            $clauses[] = "wi.assigned_user_id IN ($placeholders)";
            foreach ($teamWpUserIds as $id) {
                $params[] = $id;
            }
        }

        $whereScope = implode(' OR ', $clauses);

        $sql = $this->wpdb->prepare(
            "SELECT e.severity, COUNT(*) AS c
             FROM {$this->escalationsTable} e
             INNER JOIN {$this->workItemsTable} wi
               ON wi.source_type = %s
              AND wi.source_id = e.source_entity_id
             WHERE e.status = %s
               AND e.source_entity_type = %s
               AND $activeStatusSql
               AND ($whereScope)
             GROUP BY e.severity",
            ...$params
        );

        $rows = $this->wpdb->get_results($sql);
        $bySeverity = [];
        $total = 0;
        foreach ($rows ?: [] as $r) {
            $c = (int)$r->c;
            $total += $c;
            $bySeverity[(string)$r->severity] = $c;
        }
        ksort($bySeverity);

        return [
            'total_open' => $total,
            'by_severity' => $bySeverity,
        ];
    }
}

