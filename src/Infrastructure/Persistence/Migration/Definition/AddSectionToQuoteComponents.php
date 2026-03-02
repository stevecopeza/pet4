<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddSectionToQuoteComponents implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_quote_components';
        
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'section'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN section VARCHAR(100) NOT NULL DEFAULT 'General' AFTER type");
            $this->wpdb->query("ALTER TABLE $table_name ADD INDEX section (section)");
        }
    }

    public function getDescription(): string
    {
        return 'Add section column to quote components table';
    }
}
