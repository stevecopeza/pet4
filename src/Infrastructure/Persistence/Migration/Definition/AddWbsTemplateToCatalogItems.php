<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddWbsTemplateToCatalogItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_catalog_items';
        
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'wbs_template'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN wbs_template LONGTEXT DEFAULT NULL AFTER type");
        }
    }

    public function getDescription(): string
    {
        return 'Add wbs_template column to catalog items table';
    }
}
