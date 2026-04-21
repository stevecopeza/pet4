<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddBaselinePricesToQuoteComponents implements Migration
{
    public function up(): void
    {
        global $wpdb;

        // --- Quote catalog items: baseline_unit_sell_price ---
        $catalogTable = $wpdb->prefix . 'pet_quote_catalog_items';
        $col = $wpdb->get_row(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '{$catalogTable}'
             AND COLUMN_NAME = 'baseline_unit_sell_price'"
        );
        if (!$col) {
            $wpdb->query(
                "ALTER TABLE {$catalogTable}
                 ADD COLUMN baseline_unit_sell_price decimal(14,2) NULL DEFAULT NULL
                     COMMENT 'Rate-card price at time of adding to quote; used to calculate discount %'
                 AFTER unit_sell_price"
            );
            // Backfill existing rows: treat current sell price as the baseline
            $wpdb->query("UPDATE {$catalogTable} SET baseline_unit_sell_price = unit_sell_price WHERE baseline_unit_sell_price IS NULL");
        }

        // --- Quote tasks: baseline_sell_rate ---
        $tasksTable = $wpdb->prefix . 'pet_quote_tasks';
        $col = $wpdb->get_row(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '{$tasksTable}'
             AND COLUMN_NAME = 'baseline_sell_rate'"
        );
        if (!$col) {
            $wpdb->query(
                "ALTER TABLE {$tasksTable}
                 ADD COLUMN baseline_sell_rate decimal(12,2) NULL DEFAULT NULL
                     COMMENT 'Rate-card sell rate at time of adding to quote; used to calculate discount %'
                 AFTER sell_rate"
            );
            // Backfill existing rows: treat current sell rate as the baseline
            $wpdb->query("UPDATE {$tasksTable} SET baseline_sell_rate = sell_rate WHERE baseline_sell_rate IS NULL");
        }
    }

    public function down(): void
    {
        // Forward-only
    }

    public function getDescription(): string
    {
        return 'Add baseline_unit_sell_price to quote_catalog_items and baseline_sell_rate to quote_tasks for discount variance tracking';
    }
}
