<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateBillingExportTables implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $exports = $wpdb->prefix . 'pet_billing_exports';
        if ($wpdb->get_var("SHOW TABLES LIKE '$exports'") !== $exports) {
            $sql = "CREATE TABLE $exports (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                customer_id bigint(20) NOT NULL,
                period_start date NOT NULL,
                period_end date NOT NULL,
                status varchar(16) NOT NULL,
                created_by_employee_id bigint(20) NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uuid (uuid),
                KEY customer_status (customer_id, status),
                KEY period (period_start, period_end)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        $items = $wpdb->prefix . 'pet_billing_export_items';
        if ($wpdb->get_var("SHOW TABLES LIKE '$items'") !== $items) {
            $sql = "CREATE TABLE $items (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                export_id bigint(20) unsigned NOT NULL,
                source_type varchar(32) NOT NULL,
                source_id bigint(20) NOT NULL,
                quantity decimal(14,2) NOT NULL,
                unit_price decimal(14,2) NOT NULL,
                amount decimal(14,2) NOT NULL,
                description text NOT NULL,
                qb_item_ref varchar(128) DEFAULT NULL,
                status varchar(16) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY export_id (export_id),
                KEY source (source_type, source_id)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }

    public function getDescription(): string
    {
        return 'Create billing export tables: exports and items';
    }
}
