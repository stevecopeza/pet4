<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddManagerPriorityOverrideToWorkItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_work_items';
        
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'manager_priority_override'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN manager_priority_override DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER priority_score");
        }
    }

    public function getDescription(): string
    {
        return 'Add manager_priority_override to work_items table';
    }
}
