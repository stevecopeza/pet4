<?php

declare(strict_types=1);

namespace Pet\Application\Dashboard\Query;

class TeamSupportSummaryQuery
{
    private $wpdb;
    private string $workItemsTable;
    private string $ticketsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
        $this->ticketsTable = $wpdb->prefix . 'pet_tickets';
    }

    public function getSummaryForTeam(int $teamId): array
    {
        $teamIdStr = (string)$teamId;

        $sql = $this->wpdb->prepare(
            "SELECT
                COUNT(*) AS open_tickets,
                SUM(CASE WHEN wi.sla_time_remaining_minutes IS NOT NULL AND wi.sla_time_remaining_minutes < 0 THEN 1 ELSE 0 END) AS breached_tickets,
                SUM(CASE WHEN wi.sla_time_remaining_minutes IS NOT NULL AND wi.sla_time_remaining_minutes >= 0 AND wi.sla_time_remaining_minutes < 60 THEN 1 ELSE 0 END) AS warning_tickets,
                SUM(CASE WHEN wi.assigned_user_id IS NULL OR wi.assigned_user_id = '' THEN 1 ELSE 0 END) AS unassigned_tickets
             FROM {$this->workItemsTable} wi
             INNER JOIN {$this->ticketsTable} t ON t.id = wi.source_id
             WHERE wi.source_type = %s
               AND wi.department_id = %s
               AND t.status NOT IN ('closed','resolved')",
            'ticket',
            $teamIdStr
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!$row) {
            return [
                'open_tickets' => 0,
                'breached_tickets' => 0,
                'warning_tickets' => 0,
                'unassigned_tickets' => 0,
            ];
        }

        return [
            'open_tickets' => (int)$row['open_tickets'],
            'breached_tickets' => (int)$row['breached_tickets'],
            'warning_tickets' => (int)$row['warning_tickets'],
            'unassigned_tickets' => (int)$row['unassigned_tickets'],
        ];
    }
}

