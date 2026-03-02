<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateQuotePaymentScheduleTable implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_quote_payment_schedule';
        $charsetCollate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            amount decimal(19,4) NOT NULL DEFAULT 0.0000,
            due_date datetime DEFAULT NULL,
            paid_flag tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create table for quote payment schedules';
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_quote_payment_schedule';
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}
