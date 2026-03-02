<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateCatalogTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Catalog Items
        $sql = "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}pet_catalog_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sku varchar(50) DEFAULT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            category varchar(100) DEFAULT NULL,
            unit_price decimal(10,2) NOT NULL DEFAULT '0.00',
            unit_cost decimal(10,2) NOT NULL DEFAULT '0.00',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY sku (sku)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create catalog items table';
    }
}
