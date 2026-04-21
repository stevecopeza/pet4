<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddEscalationRuleCriteria implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_sla_escalation_rules';
        $charsetCollate = $wpdb->get_charset_collate();

        // Check if criteria_json exists
        // Note: longtext columns cannot have inline DEFAULT values in MySQL 8+,
        // so we add as NULL and backfill existing rows immediately after.
        $row = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'criteria_json'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN criteria_json longtext NULL AFTER action");
            $wpdb->query("UPDATE {$table} SET criteria_json = '{}' WHERE criteria_json IS NULL");
        }

        // Check if is_enabled exists
        $row = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'is_enabled'");
        if (empty($row)) {
            $sql = "ALTER TABLE {$table} ADD COLUMN is_enabled tinyint(1) NOT NULL DEFAULT 1 AFTER criteria_json";
            $wpdb->query($sql);
        }
    }

    public function down(): void
    {
        // Forward-only migration
    }

    public function getDescription(): string
    {
        return 'Add criteria_json and is_enabled columns to escalation rules table';
    }
}
