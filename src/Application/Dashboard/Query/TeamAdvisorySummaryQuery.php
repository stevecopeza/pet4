<?php

declare(strict_types=1);

namespace Pet\Application\Dashboard\Query;

class TeamAdvisorySummaryQuery
{
    private $wpdb;
    private string $signalsTable;
    private string $workItemsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->signalsTable = $wpdb->prefix . 'pet_advisory_signals';
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
    }

    public function getActiveSummaryForTeam(int $teamId): array
    {
        $teamIdStr = (string)$teamId;

        $sql = $this->wpdb->prepare(
            "SELECT s.severity, COUNT(*) AS c
             FROM {$this->signalsTable} s
             INNER JOIN {$this->workItemsTable} wi ON wi.id = s.work_item_id
             WHERE s.status = %s AND wi.department_id = %s
             GROUP BY s.severity",
            'ACTIVE',
            $teamIdStr
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
            'total_active' => $total,
            'by_severity' => $bySeverity,
        ];
    }
}

