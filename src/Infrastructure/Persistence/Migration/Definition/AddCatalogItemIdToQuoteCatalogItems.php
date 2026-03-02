<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddCatalogItemIdToQuoteCatalogItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_quote_catalog_items';
        // Add catalog_item_id if it doesn't exist
        $row = $this->wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = 'catalog_item_id'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN catalog_item_id mediumint(9) DEFAULT NULL AFTER component_id");
        }
        
        // Add wbs_snapshot if it doesn't exist
        $row = $this->wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = 'wbs_snapshot'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN wbs_snapshot LONGTEXT DEFAULT NULL AFTER catalog_item_id");
        }
    }

    public function getDescription(): string
    {
        return 'Adds catalog_item_id and wbs_snapshot columns to pet_quote_catalog_items table.';
    }
}
