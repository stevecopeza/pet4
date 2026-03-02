<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddRoleIdToTasks implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_tasks';
        
        // Check if column exists
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'role_id'");
        
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER estimated_hours");
            $this->wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_role_id (role_id)");
        }
    }

    public function down(): void
    {
        // No down migration
    }

    public function getDescription(): string
    {
        return 'Add role_id column to tasks table';
    }
}
