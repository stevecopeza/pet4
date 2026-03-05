<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddServiceTypeAndRateCardToQuoteTasks implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_quote_tasks';

        $colExists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'service_type_id'");
        if (empty($colExists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN service_type_id bigint(20) unsigned DEFAULT NULL AFTER sell_rate");
        }

        $colExists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'rate_card_id'");
        if (empty($colExists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN rate_card_id bigint(20) unsigned DEFAULT NULL AFTER service_type_id");
        }
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_quote_tasks';
        $wpdb->query("ALTER TABLE $table DROP COLUMN IF EXISTS service_type_id");
        $wpdb->query("ALTER TABLE $table DROP COLUMN IF EXISTS rate_card_id");
    }

    public function getDescription(): string
    {
        return 'Add service_type_id and rate_card_id columns to quote tasks table';
    }
}
