<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateWorkItemsTableAddRevenueAndTier implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_work_items';
        
        // Check if columns exist to avoid errors on re-run
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'revenue'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN revenue DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER status");
        }

        $row = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'client_tier'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN client_tier INT NOT NULL DEFAULT 1 AFTER revenue");
        }
    }

    public function getDescription(): string
    {
        return 'Add revenue and client_tier columns to work_items table';
    }
}
