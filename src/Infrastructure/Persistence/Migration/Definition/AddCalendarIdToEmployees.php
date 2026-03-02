<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddCalendarIdToEmployees implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_employees';
        
        // Add calendar_id column if it doesn't exist
        $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'calendar_id'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN calendar_id int(11) DEFAULT NULL AFTER manager_id");
            $wpdb->query("ALTER TABLE $table ADD INDEX calendar_id (calendar_id)");
        }
    }

    public function getDescription(): string
    {
        return 'Add calendar_id to employees table';
    }
}
