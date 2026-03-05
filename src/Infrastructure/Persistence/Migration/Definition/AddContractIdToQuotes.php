<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddContractIdToQuotes implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_quotes';

        $colExists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'contract_id'");
        if (empty($colExists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN contract_id bigint(20) unsigned DEFAULT NULL AFTER lead_id");
        }
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_quotes';
        $wpdb->query("ALTER TABLE $table DROP COLUMN IF EXISTS contract_id");
    }

    public function getDescription(): string
    {
        return 'Add contract_id to quotes table for rate card resolution scope';
    }
}
