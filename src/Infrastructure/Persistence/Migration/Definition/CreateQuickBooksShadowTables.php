<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateQuickBooksShadowTables implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $invoices = $wpdb->prefix . 'pet_qb_invoices';
        if ($wpdb->get_var("SHOW TABLES LIKE '$invoices'") !== $invoices) {
            $sql = "CREATE TABLE $invoices (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                customer_id bigint(20) NOT NULL,
                qb_invoice_id varchar(128) NOT NULL,
                doc_number varchar(64) DEFAULT NULL,
                status varchar(32) NOT NULL,
                issue_date date NOT NULL,
                due_date date DEFAULT NULL,
                currency varchar(8) NOT NULL,
                total decimal(14,2) NOT NULL,
                balance decimal(14,2) NOT NULL,
                raw_json longtext NOT NULL,
                last_synced_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY qb_invoice_id (qb_invoice_id),
                KEY customer_balance (customer_id, balance),
                KEY last_synced_at (last_synced_at)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        $payments = $wpdb->prefix . 'pet_qb_payments';
        if ($wpdb->get_var("SHOW TABLES LIKE '$payments'") !== $payments) {
            $sql = "CREATE TABLE $payments (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                customer_id bigint(20) NOT NULL,
                qb_payment_id varchar(128) NOT NULL,
                received_date date NOT NULL,
                amount decimal(14,2) NOT NULL,
                currency varchar(8) NOT NULL,
                applied_invoices_json longtext DEFAULT NULL,
                raw_json longtext NOT NULL,
                last_synced_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY qb_payment_id (qb_payment_id),
                KEY customer_date (customer_id, received_date),
                KEY last_synced_at (last_synced_at)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }

    public function getDescription(): string
    {
        return 'Create QuickBooks shadow read model tables: invoices and payments';
    }
}
