<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTieredSlaSupport implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $slasTable = $wpdb->prefix . 'pet_slas';
        $tiersTable = $wpdb->prefix . 'pet_sla_tiers';
        $tierRulesTable = $wpdb->prefix . 'pet_sla_tier_escalation_rules';
        $calendarsTable = $wpdb->prefix . 'pet_calendars';

        // 1. Add tier_transition_cap_percent to existing SLAs table
        $colExists = $wpdb->get_results("SHOW COLUMNS FROM $slasTable LIKE 'tier_transition_cap_percent'");
        if (empty($colExists)) {
            $wpdb->query("ALTER TABLE $slasTable ADD COLUMN tier_transition_cap_percent int(11) NOT NULL DEFAULT 80 AFTER resolution_target_minutes");
        }

        // 2. Make calendar_id and flat targets nullable for tiered mode
        $wpdb->query("ALTER TABLE $slasTable MODIFY COLUMN calendar_id bigint(20) unsigned NULL");
        $wpdb->query("ALTER TABLE $slasTable MODIFY COLUMN response_target_minutes int(11) NULL");
        $wpdb->query("ALTER TABLE $slasTable MODIFY COLUMN resolution_target_minutes int(11) NULL");

        // 3. SLA Tiers Table
        if ($wpdb->get_var("SHOW TABLES LIKE '$tiersTable'") !== $tiersTable) {
            $sqlTiers = "CREATE TABLE $tiersTable (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                sla_id bigint(20) unsigned NOT NULL,
                priority int(11) NOT NULL,
                label varchar(100) NOT NULL DEFAULT '',
                calendar_id bigint(20) unsigned NOT NULL,
                response_target_minutes int(11) NOT NULL,
                resolution_target_minutes int(11) NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY sla_priority (sla_id, priority),
                KEY calendar_id (calendar_id),
                FOREIGN KEY (sla_id) REFERENCES $slasTable(id) ON DELETE CASCADE,
                FOREIGN KEY (calendar_id) REFERENCES $calendarsTable(id)
            ) $charsetCollate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sqlTiers);
        }

        // 4. SLA Tier Escalation Rules Table
        if ($wpdb->get_var("SHOW TABLES LIKE '$tierRulesTable'") !== $tierRulesTable) {
            $sqlTierRules = "CREATE TABLE $tierRulesTable (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                sla_tier_id bigint(20) unsigned NOT NULL,
                threshold_percent int(11) NOT NULL,
                action varchar(50) NOT NULL DEFAULT 'notify_manager',
                PRIMARY KEY  (id),
                KEY sla_tier_id (sla_tier_id),
                FOREIGN KEY (sla_tier_id) REFERENCES $tiersTable(id) ON DELETE CASCADE
            ) $charsetCollate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sqlTierRules);
        }
    }

    public function down(): void
    {
        global $wpdb;

        $tierRulesTable = $wpdb->prefix . 'pet_sla_tier_escalation_rules';
        $tiersTable = $wpdb->prefix . 'pet_sla_tiers';
        $slasTable = $wpdb->prefix . 'pet_slas';

        $wpdb->query("DROP TABLE IF EXISTS $tierRulesTable");
        $wpdb->query("DROP TABLE IF EXISTS $tiersTable");
        $wpdb->query("ALTER TABLE $slasTable DROP COLUMN IF EXISTS tier_transition_cap_percent");
    }

    public function getDescription(): string
    {
        return 'Add tiered SLA support: tier_transition_cap_percent column, sla_tiers and tier_escalation_rules tables';
    }
}
