<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTypeToCatalogItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_catalog_items';
        
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'type'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN type VARCHAR(50) NOT NULL DEFAULT 'product' AFTER sku");
        }
        $indexExists = $this->wpdb->get_var(
            $this->wpdb->prepare("SHOW INDEX FROM $table_name WHERE Key_name = %s", 'type')
        );
        if (!$indexExists) {
            $this->wpdb->query("ALTER TABLE $table_name ADD INDEX type (type)");
        }
    }

    public function getDescription(): string
    {
        return 'Add type column to catalog items table';
    }
}
