<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddSkuAndRoleIdToQuoteCatalogItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_quote_catalog_items';
        
        // Add sku if it doesn't exist
        $row = $this->wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = 'sku'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN sku VARCHAR(100) DEFAULT NULL AFTER description");
        }
        
        // Add role_id if it doesn't exist
        $row = $this->wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = 'role_id'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN role_id mediumint(9) DEFAULT NULL AFTER sku");
        }

        // Add type if it doesn't exist
        $row = $this->wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = 'type'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN type VARCHAR(50) NOT NULL DEFAULT 'service' AFTER component_id");
        }
    }

    public function getDescription(): string
    {
        return 'Adds sku, role_id, and type columns to pet_quote_catalog_items table.';
    }
}
