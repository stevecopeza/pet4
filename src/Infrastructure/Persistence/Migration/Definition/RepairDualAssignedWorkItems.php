<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class RepairDualAssignedWorkItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        $tableExists = $this->wpdb->get_var($this->wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($tableExists !== $table) {
            return;
        }

        $this->wpdb->query(
            "UPDATE $table
             SET assigned_team_id = NULL
             WHERE assigned_team_id IS NOT NULL
               AND assigned_team_id <> ''
               AND assigned_user_id IS NOT NULL
               AND assigned_user_id <> ''"
        );
    }

    public function getDescription(): string
    {
        return 'Repair invalid work items by clearing assigned_team_id when assigned_user_id is set.';
    }
}

