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
        $row = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'criteria_json'");
        if (empty($row)) {
            $sql = "ALTER TABLE {$table} ADD COLUMN criteria_json longtext NOT NULL DEFAULT '{}' AFTER action";
            $wpdb->query($sql);
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
