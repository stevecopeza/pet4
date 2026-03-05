<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateCatalogProductsTable implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'pet_catalog_products';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $sql = "CREATE TABLE $table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                sku varchar(50) NOT NULL,
                name varchar(255) NOT NULL,
                description text DEFAULT NULL,
                category varchar(100) DEFAULT NULL,
                unit_price decimal(12,2) NOT NULL DEFAULT '0.00',
                unit_cost decimal(12,2) NOT NULL DEFAULT '0.00',
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY sku (sku)
            ) $charsetCollate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_catalog_products';
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    public function getDescription(): string
    {
        return 'Create catalog products table (products only, replaces product-type CatalogItems)';
    }
}
