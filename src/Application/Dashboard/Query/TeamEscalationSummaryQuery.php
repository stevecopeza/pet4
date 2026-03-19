<?php

declare(strict_types=1);

namespace Pet\Application\Dashboard\Query;

class TeamEscalationSummaryQuery
{
    private $wpdb;
    private string $escalationsTable;
    private string $workItemsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->escalationsTable = $wpdb->prefix . 'pet_escalations';
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
    }

    public function getOpenSummaryForTeam(int $teamId): array
    {
        $teamIdStr = (string)$teamId;

        $sql = $this->wpdb->prepare(
            "SELECT e.severity, COUNT(*) AS c
             FROM {$this->escalationsTable} e
             INNER JOIN {$this->workItemsTable} wi
               ON wi.source_type = %s
              AND wi.source_id = e.source_entity_id
             WHERE e.status = %s
               AND e.source_entity_type = %s
               AND wi.department_id = %s
             GROUP BY e.severity",
            'ticket',
            'OPEN',
            'ticket',
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
            'total_open' => $total,
            'by_severity' => $bySeverity,
        ];
    }
}

