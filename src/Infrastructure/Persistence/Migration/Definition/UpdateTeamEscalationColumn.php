<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateTeamEscalationColumn implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $teamsTable = $this->wpdb->prefix . 'pet_teams';
        
        // Check if old column exists
        $columns = $this->wpdb->get_col("DESCRIBE $teamsTable", 0);
        if (in_array('escalation_team_id', $columns)) {
            // Check if new column already exists (from CreateTeamTables running first)
            if (in_array('escalation_manager_id', $columns)) {
                // If both exist, drop the old one
                $this->wpdb->query("ALTER TABLE $teamsTable DROP COLUMN escalation_team_id");
            } else {
                // Rename existing column (clearing data first as types/semantics differ)
                $this->wpdb->query("UPDATE $teamsTable SET escalation_team_id = NULL");
                $this->wpdb->query("ALTER TABLE $teamsTable CHANGE escalation_team_id escalation_manager_id bigint(20) UNSIGNED DEFAULT NULL");
                $this->wpdb->query("ALTER TABLE $teamsTable DROP INDEX escalation_team_id");
                $this->wpdb->query("ALTER TABLE $teamsTable ADD INDEX escalation_manager_id (escalation_manager_id)");
            }
        }
    }

    public function getDescription(): string
    {
        return 'Rename escalation_team_id to escalation_manager_id in pet_teams table.';
    }
}
