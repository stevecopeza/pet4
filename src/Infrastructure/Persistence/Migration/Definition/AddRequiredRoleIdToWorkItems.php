<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddRequiredRoleIdToWorkItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_work_items';
        
        // Check if column exists
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'required_role_id'");
        
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN required_role_id BIGINT UNSIGNED NULL AFTER department_id");
            $this->wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_required_role (required_role_id)");
        }
    }

    public function getDescription(): string
    {
        return 'Add required_role_id column to work_items table';
    }
}
