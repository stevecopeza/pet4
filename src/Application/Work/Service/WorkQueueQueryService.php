<?php

declare(strict_types=1);

namespace Pet\Application\Work\Service;

class WorkQueueQueryService
{
    private $wpdb;
    private string $workItemsTable;
    private string $ticketsTable;
    private ?array $ticketColumns = null;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
        $this->ticketsTable = $wpdb->prefix . 'pet_tickets';
    }

    public function countByQueueKeys(array $queueKeys): array
    {
        $out = [];
        foreach ($queueKeys as $k) {
            $out[$k] = $this->countForQueue($k);
        }
        ksort($out);
        return $out;
    }

    public function listItemsForQueue(string $queueKey): array
    {
        [$where, $params, $domain] = $this->whereForQueue($queueKey);
        if ($where === null) {
            return [];
        }
        [$ticketSelectSql, $ticketJoinSql, $lifecycleFilterSql] = $this->ticketJoinFragments($domain, $params);

        $sql = $this->wpdb->prepare(
            "SELECT
                wi.*,
                $ticketSelectSql
             FROM {$this->workItemsTable} wi
             $ticketJoinSql
             WHERE $where
               AND wi.status IN ('active', 'waiting')$lifecycleFilterSql
             ORDER BY wi.priority_score DESC, wi.updated_at DESC",
            ...$params
        );
        $rows = $this->wpdb->get_results($sql);
        if (!$rows) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $sourceType = (string)$row->source_type;
            $sourceId = (string)$row->source_id;

            $reference = strtoupper($sourceType) . '-' . $sourceId;
            $title = null;
            $customerId = null;
            $siteId = null;
            $status = null;
            $priority = (float)$row->priority_score;
            $dueAt = $row->scheduled_due_utc ? (string)$row->scheduled_due_utc : null;
            $projectId = null;

            if ($sourceType === 'ticket') {
                $reference = 'TICKET-' . $sourceId;
                $title = isset($row->ticket_subject) ? (string)$row->ticket_subject : null;
                $customerId = isset($row->ticket_customer_id) && $row->ticket_customer_id !== null ? (int)$row->ticket_customer_id : null;
                $siteId = isset($row->ticket_site_id) && $row->ticket_site_id !== null ? (int)$row->ticket_site_id : null;
                $status = isset($row->ticket_status) ? (string)$row->ticket_status : null;
                $priority = $this->priorityScoreFromTicketPriority(isset($row->ticket_priority) ? (string)$row->ticket_priority : null) ?? (float)$row->priority_score;
                $dueAt = isset($row->ticket_due_at) && $row->ticket_due_at ? (string)$row->ticket_due_at : $dueAt;
                $projectId = isset($row->ticket_project_id) && $row->ticket_project_id !== null ? (int)$row->ticket_project_id : null;
            }

            $out[] = [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reference_code' => $reference,
                'title' => $title,
                'customer_id' => $customerId,
                'site_id' => $siteId,
                'status' => $status,
                'priority' => $priority,
                'assignment_mode' => $row->assignment_mode !== null ? (string)$row->assignment_mode : $this->deriveAssignmentMode($row),
                'department_id' => $row->department_id !== null ? (string)$row->department_id : null,
                'assigned_team_id' => isset($row->assigned_team_id) ? ($row->assigned_team_id !== null ? (string)$row->assigned_team_id : null) : null,
                'assigned_user_id' => $row->assigned_user_id !== null ? (string)$row->assigned_user_id : null,
                'queue_key' => $queueKey,
                'created_at' => (string)$row->created_at,
                'updated_at' => (string)$row->updated_at,
                'due_at' => $dueAt,
                'sla_state' => null,
                'project_id' => $projectId,
                'routing_reason' => isset($row->routing_reason) ? ($row->routing_reason !== null ? (string)$row->routing_reason : null) : null,
            ];
        }

        return $out;
    }

    private function deriveAssignmentMode(object $row): string
    {
        $user = $row->assigned_user_id !== null ? (string)$row->assigned_user_id : '';
        $team = isset($row->assigned_team_id) && $row->assigned_team_id !== null ? (string)$row->assigned_team_id : '';
        if ($user !== '') {
            return 'USER_ASSIGNED';
        }
        if ($team !== '') {
            return 'TEAM_QUEUE';
        }
        return 'UNROUTED';
    }

    private function priorityScoreFromTicketPriority(?string $priority): ?float
    {
        if ($priority === null || $priority === '') {
            return null;
        }

        return match ($priority) {
            'critical' => 100.0,
            'high' => 75.0,
            'medium' => 50.0,
            'low' => 25.0,
            default => null,
        };
    }

    private function countForQueue(string $queueKey): int
    {
        [$where, $params, $domain] = $this->whereForQueue($queueKey);
        if ($where === null) {
            return 0;
        }
        [, $ticketJoinSql, $lifecycleFilterSql] = $this->ticketJoinFragments($domain, $params);

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->workItemsTable} wi
             $ticketJoinSql
             WHERE $where
               AND wi.status IN ('active', 'waiting')$lifecycleFilterSql",
            ...$params
        );
        return (int)$this->wpdb->get_var($sql);
    }

    private function whereForQueue(string $queueKey): array
    {
        $parts = explode(':', $queueKey);
        if (count($parts) < 2) {
            return [null, [], null];
        }

        $domain = $parts[0];
        $kind = $parts[1];
        $id = $parts[2] ?? null;

        $sourceType = match ($domain) {
            'support', 'delivery' => 'ticket',
            'escalation', 'admin' => $domain,
            default => null,
        };
        if ($sourceType === null) {
            return [null, [], null];
        }

        if ($kind === 'user' && $id !== null) {
            return ["wi.source_type = %s AND wi.assigned_user_id = %s", [$sourceType, $id], $domain];
        }

        if ($kind === 'team' && $id !== null) {
            return ["wi.source_type = %s AND wi.assigned_team_id = %s AND (wi.assigned_user_id IS NULL OR wi.assigned_user_id = '')", [$sourceType, $id], $domain];
        }

        if ($kind === 'unrouted') {
            return ["wi.source_type = %s AND (wi.assigned_user_id IS NULL OR wi.assigned_user_id = '') AND (wi.assigned_team_id IS NULL OR wi.assigned_team_id = '')", [$sourceType], $domain];
        }

        return [null, [], null];
    }

    private function supportsTicketLifecycleFiltering(): bool
    {
        return $this->ticketTableExists() && $this->hasTicketColumn('lifecycle_owner');
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $tableExists = $this->wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($tableExists !== $table) {
            return false;
        }

        $columns = $this->wpdb->get_col("DESCRIBE $table", 0);
        return is_array($columns) && in_array($column, $columns, true);
    }

    private function ticketTableExists(): bool
    {
        return $this->wpdb->get_var("SHOW TABLES LIKE '$this->ticketsTable'") === $this->ticketsTable;
    }

    private function hasTicketColumn(string $column): bool
    {
        if (!$this->ticketTableExists()) {
            return false;
        }

        if ($this->ticketColumns === null) {
            $columns = $this->wpdb->get_col("DESCRIBE {$this->ticketsTable}", 0);
            $this->ticketColumns = is_array($columns) ? array_map('strval', $columns) : [];
        }

        return in_array($column, $this->ticketColumns, true);
    }

    private function ticketSelectExpression(string $column, string $alias): string
    {
        if ($this->ticketTableExists() && $this->hasTicketColumn($column)) {
            return "t.$column AS $alias";
        }

        return "NULL AS $alias";
    }

    private function ticketJoinFragments(?string $domain, array &$params): array
    {
        $select = implode(",\n                ", [
            $this->ticketSelectExpression('subject', 'ticket_subject'),
            $this->ticketSelectExpression('customer_id', 'ticket_customer_id'),
            $this->ticketSelectExpression('site_id', 'ticket_site_id'),
            $this->ticketSelectExpression('status', 'ticket_status'),
            $this->ticketSelectExpression('priority', 'ticket_priority'),
            $this->ticketSelectExpression('resolution_due_at', 'ticket_due_at'),
            $this->ticketSelectExpression('project_id', 'ticket_project_id'),
        ]);

        $join = '';
        $lifecycleFilter = '';
        if ($this->ticketTableExists()) {
            $join = "LEFT JOIN {$this->ticketsTable} t\n               ON wi.source_type = 'ticket'\n              AND wi.source_id = t.id";
            if ($this->supportsTicketLifecycleFiltering() && in_array($domain, ['support', 'delivery'], true)) {
                $lifecycleOwner = $domain === 'delivery' ? 'project' : 'support';
                $lifecycleFilter = ' AND t.lifecycle_owner = %s';
                $params[] = $lifecycleOwner;
            }
        }

        return [$select, $join, $lifecycleFilter];
    }
}
