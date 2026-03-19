<?php

declare(strict_types=1);

namespace Pet\Application\Work\Service;

class WorkQueueQueryService
{
    private $wpdb;
    private string $workItemsTable;
    private string $ticketsTable;
    private string $tasksTable;
    private string $projectsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
        $this->ticketsTable = $wpdb->prefix . 'pet_tickets';
        $this->tasksTable = $wpdb->prefix . 'pet_tasks';
        $this->projectsTable = $wpdb->prefix . 'pet_projects';
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
        [$where, $params] = $this->whereForQueue($queueKey);
        if ($where === null) {
            return [];
        }

        $sql = $this->wpdb->prepare(
            "SELECT *
             FROM {$this->workItemsTable}
             WHERE $where
             AND status IN ('active', 'waiting')
             ORDER BY priority_score DESC, updated_at DESC",
            ...$params
        );
        $rows = $this->wpdb->get_results($sql);
        if (!$rows) {
            return [];
        }

        $tickets = $this->loadTickets($rows);
        $delivery = $this->loadDeliveryTasks($rows);

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

            if ($sourceType === 'ticket' && isset($tickets[$sourceId])) {
                $t = $tickets[$sourceId];
                $reference = 'TICKET-' . $sourceId;
                $title = $t['subject'];
                $customerId = $t['customer_id'];
                $siteId = $t['site_id'];
                $status = $t['status'];
                $priority = $t['priority_score'];
                $dueAt = $t['due_at'];
            } elseif ($sourceType === 'project_task' && isset($delivery[$sourceId])) {
                $d = $delivery[$sourceId];
                $reference = 'TASK-' . $sourceId;
                $title = $d['name'];
                $customerId = $d['customer_id'];
                $status = $d['status'];
                $projectId = $d['project_id'];
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

    private function loadTickets(array $workRows): array
    {
        $ids = [];
        foreach ($workRows as $row) {
            if ((string)$row->source_type === 'ticket') {
                $ids[] = (int)$row->source_id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = $this->wpdb->prepare(
            "SELECT id, subject, customer_id, site_id, status, priority, resolution_due_at
             FROM {$this->ticketsTable}
             WHERE id IN ($placeholders)",
            ...$ids
        );
        $rows = $this->wpdb->get_results($sql);

        $out = [];
        foreach ($rows as $r) {
            $priorityScore = match ((string)$r->priority) {
                'critical' => 100.0,
                'high' => 75.0,
                'medium' => 50.0,
                'low' => 25.0,
                default => 50.0,
            };

            $out[(string)$r->id] = [
                'subject' => (string)$r->subject,
                'customer_id' => (int)$r->customer_id,
                'site_id' => $r->site_id !== null ? (int)$r->site_id : null,
                'status' => (string)$r->status,
                'priority_score' => $priorityScore,
                'due_at' => $r->resolution_due_at ? (string)$r->resolution_due_at : null,
            ];
        }
        return $out;
    }

    private function loadDeliveryTasks(array $workRows): array
    {
        $ids = [];
        foreach ($workRows as $row) {
            if ((string)$row->source_type === 'project_task') {
                $ids[] = (int)$row->source_id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = $this->wpdb->prepare(
            "SELECT t.id, t.project_id, t.name, t.is_completed, p.customer_id
             FROM {$this->tasksTable} t
             LEFT JOIN {$this->projectsTable} p ON p.id = t.project_id
             WHERE t.id IN ($placeholders)",
            ...$ids
        );
        $rows = $this->wpdb->get_results($sql);

        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r->id] = [
                'project_id' => (int)$r->project_id,
                'name' => (string)$r->name,
                'status' => ((int)$r->is_completed) === 1 ? 'completed' : 'active',
                'customer_id' => $r->customer_id !== null ? (int)$r->customer_id : null,
            ];
        }
        return $out;
    }

    private function countForQueue(string $queueKey): int
    {
        [$where, $params] = $this->whereForQueue($queueKey);
        if ($where === null) {
            return 0;
        }

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->workItemsTable}
             WHERE $where
             AND status IN ('active', 'waiting')",
            ...$params
        );
        return (int)$this->wpdb->get_var($sql);
    }

    private function whereForQueue(string $queueKey): array
    {
        $parts = explode(':', $queueKey);
        if (count($parts) < 2) {
            return [null, []];
        }

        $domain = $parts[0];
        $kind = $parts[1];
        $id = $parts[2] ?? null;

        $sourceType = match ($domain) {
            'support' => 'ticket',
            'delivery' => 'project_task',
            default => null,
        };
        if ($sourceType === null) {
            return [null, []];
        }

        if ($kind === 'user' && $id !== null) {
            return ["source_type = %s AND assigned_user_id = %s", [$sourceType, $id]];
        }

        if ($kind === 'team' && $id !== null) {
            return ["source_type = %s AND assigned_team_id = %s AND (assigned_user_id IS NULL OR assigned_user_id = '')", [$sourceType, $id]];
        }

        if ($kind === 'unrouted') {
            if ($sourceType !== 'ticket') {
                return [null, []];
            }
            return ["source_type = %s AND (assigned_user_id IS NULL OR assigned_user_id = '') AND (assigned_team_id IS NULL OR assigned_team_id = '')", [$sourceType]];
        }

        return [null, []];
    }
}
