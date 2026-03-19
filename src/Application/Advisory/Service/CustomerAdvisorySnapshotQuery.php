<?php

declare(strict_types=1);

namespace Pet\Application\Advisory\Service;

class CustomerAdvisorySnapshotQuery
{
    private $wpdb;
    private string $ticketsTable;
    private string $projectsTable;
    private string $tasksTable;
    private string $workItemsTable;
    private string $signalsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->ticketsTable = $wpdb->prefix . 'pet_tickets';
        $this->projectsTable = $wpdb->prefix . 'pet_projects';
        $this->tasksTable = $wpdb->prefix . 'pet_tasks';
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
        $this->signalsTable = $wpdb->prefix . 'pet_advisory_signals';
    }

    public function snapshotForCustomer(int $customerId): array
    {
        $tickets = $this->ticketCounts($customerId);
        $projects = $this->projectCounts($customerId);
        $taskCounts = $this->taskCounts($customerId);
        $signals = $this->activeSignalSummary($customerId);

        return [
            'customer_id' => $customerId,
            'tickets' => $tickets,
            'projects' => $projects,
            'tasks' => $taskCounts,
            'signals' => $signals,
            'generated_at_utc' => gmdate('c'),
        ];
    }

    private function ticketCounts(int $customerId): array
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT status, COUNT(*) AS c
             FROM {$this->ticketsTable}
             WHERE customer_id = %d
             GROUP BY status",
            $customerId
        ));
        $byStatus = [];
        foreach ($rows ?: [] as $r) {
            $byStatus[(string)$r->status] = (int)$r->c;
        }

        $overdue = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->ticketsTable}
             WHERE customer_id = %d AND resolution_due_at IS NOT NULL AND resolution_due_at < %s
             AND status NOT IN ('closed','resolved')",
            $customerId,
            gmdate('Y-m-d H:i:s')
        ));

        return [
            'by_status' => $byStatus,
            'overdue' => $overdue,
        ];
    }

    private function projectCounts(int $customerId): array
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT state, COUNT(*) AS c
             FROM {$this->projectsTable}
             WHERE customer_id = %d AND archived_at IS NULL
             GROUP BY state",
            $customerId
        ));
        $byState = [];
        foreach ($rows ?: [] as $r) {
            $byState[(string)$r->state] = (int)$r->c;
        }

        return [
            'by_state' => $byState,
        ];
    }

    private function taskCounts(int $customerId): array
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT t.is_completed, COUNT(*) AS c
             FROM {$this->tasksTable} t
             INNER JOIN {$this->projectsTable} p ON p.id = t.project_id
             WHERE p.customer_id = %d AND p.archived_at IS NULL
             GROUP BY t.is_completed",
            $customerId
        ));
        $completed = 0;
        $open = 0;
        foreach ($rows ?: [] as $r) {
            if (((int)$r->is_completed) === 1) {
                $completed = (int)$r->c;
            } else {
                $open = (int)$r->c;
            }
        }
        return [
            'open' => $open,
            'completed' => $completed,
        ];
    }

    private function activeSignalSummary(int $customerId): array
    {
        $workItemIds = $this->workItemIdsForCustomer($customerId);
        if (empty($workItemIds)) {
            return ['total_active' => 0, 'by_severity' => [], 'by_type' => []];
        }

        $placeholders = implode(',', array_fill(0, count($workItemIds), '%s'));
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT severity, signal_type, COUNT(*) AS c
             FROM {$this->signalsTable}
             WHERE status = %s AND work_item_id IN ($placeholders)
             GROUP BY severity, signal_type",
            ...array_merge(['ACTIVE'], $workItemIds)
        ));

        $bySeverity = [];
        $byType = [];
        $total = 0;
        foreach ($rows ?: [] as $r) {
            $c = (int)$r->c;
            $total += $c;
            $sev = (string)$r->severity;
            $type = (string)$r->signal_type;
            $bySeverity[$sev] = ($bySeverity[$sev] ?? 0) + $c;
            $byType[$type] = ($byType[$type] ?? 0) + $c;
        }

        ksort($bySeverity);
        ksort($byType);

        return [
            'total_active' => $total,
            'by_severity' => $bySeverity,
            'by_type' => $byType,
        ];
    }

    private function workItemIdsForCustomer(int $customerId): array
    {
        $ticketWork = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT wi.id
             FROM {$this->workItemsTable} wi
             INNER JOIN {$this->ticketsTable} t ON t.id = wi.source_id
             WHERE wi.source_type = %s AND t.customer_id = %d",
            'ticket',
            $customerId
        ));

        $taskWork = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT wi.id
             FROM {$this->workItemsTable} wi
             INNER JOIN {$this->tasksTable} task ON task.id = wi.source_id
             INNER JOIN {$this->projectsTable} p ON p.id = task.project_id
             WHERE wi.source_type = %s AND p.customer_id = %d",
            'project_task',
            $customerId
        ));

        $ids = array_merge($ticketWork ?: [], $taskWork ?: []);
        $ids = array_values(array_unique(array_map('strval', $ids)));
        return $ids;
    }
}

