<?php

declare(strict_types=1);

namespace Pet\Application\Resilience\Query;

class TeamWorkloadConcentrationQuery
{
    private $wpdb;
    private string $workItemsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
    }

    public function countOpenAssignedByUserForTeam(int $teamId): array
    {
        $teamIdStr = (string)$teamId;

        $sql = $this->wpdb->prepare(
            "SELECT assigned_user_id, COUNT(*) AS c
             FROM {$this->workItemsTable}
             WHERE department_id = %s
               AND assigned_user_id IS NOT NULL
               AND assigned_user_id <> ''
               AND status <> %s
             GROUP BY assigned_user_id",
            $teamIdStr,
            'completed'
        );

        $rows = $this->wpdb->get_results($sql);
        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[(string)$r->assigned_user_id] = (int)$r->c;
        }
        return $out;
    }
}

