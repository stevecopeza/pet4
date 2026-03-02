<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddMalleableToTimeEntries implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_time_entries';
        
        // Add malleable_data if it doesn't exist
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'malleable_data'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN malleable_data longtext NULL AFTER status");
        }

        // Add archived_at if it doesn't exist
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'archived_at'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN archived_at datetime NULL AFTER updated_at");
        }
    }

    public function getDescription(): string
    {
        return 'Add malleable_data and archived_at to time_entries table.';
    }
}
