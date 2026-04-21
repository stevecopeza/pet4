<?php

declare(strict_types=1);

namespace Pet\Application\Advisory\Service;

class CustomerAdvisorySnapshotQuery
{
    private $wpdb;
    private string $ticketsTable;
    private string $projectsTable;
    private string $workItemsTable;
    private string $signalsTable;
    private ?array $ticketColumns = null;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->ticketsTable = $wpdb->prefix . 'pet_tickets';
        $this->projectsTable = $wpdb->prefix . 'pet_projects';
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
        $this->signalsTable = $wpdb->prefix . 'pet_advisory_signals';
    }

    public function snapshotForCustomer(int $customerId): array
    {
        $tickets = $this->ticketCounts($customerId);
        $projects = $this->projectCounts($customerId);
        $deliveryTicketCounts = $this->deliveryTicketCounts($customerId);
        $signals = $this->activeSignalSummary($customerId);

        return [
            'customer_id' => $customerId,
            'tickets' => $tickets,
            'projects' => $projects,
            'delivery_tickets' => $deliveryTicketCounts,
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

    private function deliveryTicketCounts(int $customerId): array
    {
        $where = 'customer_id = %d';
        $params = [$customerId];
        if ($this->hasTicketColumn('lifecycle_owner')) {
            $where .= ' AND lifecycle_owner = %s';
            $params[] = 'project';
        } elseif ($this->hasTicketColumn('project_id')) {
            $where .= ' AND project_id IS NOT NULL';
        }

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT status, COUNT(*) AS c
             FROM {$this->ticketsTable}
             WHERE $where
             GROUP BY status",
            ...$params
        ));

        $byStatus = [];
        $closed = 0;
        $total = 0;
        foreach ($rows ?: [] as $r) {
            $status = (string)$r->status;
            $count = (int)$r->c;
            $byStatus[$status] = $count;
            $total += $count;
            if (in_array($status, ['completed', 'resolved', 'closed'], true)) {
                $closed += $count;
            }
        }

        ksort($byStatus);
        return [
            'by_status' => $byStatus,
            'open' => max(0, $total - $closed),
            'closed' => $closed,
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

        return array_values(array_unique(array_map('strval', $ticketWork ?: [])));
    }

    private function hasTicketColumn(string $column): bool
    {
        if ($this->ticketColumns === null) {
            $this->ticketColumns = [];
            $tableExists = $this->wpdb->get_var("SHOW TABLES LIKE '$this->ticketsTable'") === $this->ticketsTable;
            if ($tableExists) {
                $columns = $this->wpdb->get_col("DESCRIBE {$this->ticketsTable}", 0);
                if (is_array($columns)) {
                    $this->ticketColumns = array_map('strval', $columns);
                }
            }
        }

        return in_array($column, $this->ticketColumns, true);
    }
}

