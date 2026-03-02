<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTitleDescriptionToQuotes implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_quotes';
        
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'title'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER customer_id");
        }
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'description'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN description TEXT NULL AFTER title");
        }
    }

    public function getDescription(): string
    {
        return 'Add title and description columns to quotes table';
    }
}
