<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTierTrackingToClockState implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $clockTable = $wpdb->prefix . 'pet_sla_clock_state';
        $transitionsTable = $wpdb->prefix . 'pet_sla_clock_tier_transitions';

        // 1. Add tier-tracking columns to clock state
        $colExists = $wpdb->get_results("SHOW COLUMNS FROM $clockTable LIKE 'active_tier_priority'");
        if (empty($colExists)) {
            $wpdb->query("ALTER TABLE $clockTable ADD COLUMN active_tier_priority int(11) DEFAULT NULL AFTER last_event_dispatched");
            $wpdb->query("ALTER TABLE $clockTable ADD COLUMN tier_elapsed_business_minutes int(11) NOT NULL DEFAULT 0 AFTER active_tier_priority");
            $wpdb->query("ALTER TABLE $clockTable ADD COLUMN carried_forward_percent decimal(5,2) DEFAULT NULL AFTER tier_elapsed_business_minutes");
            $wpdb->query("ALTER TABLE $clockTable ADD COLUMN total_transitions int(11) NOT NULL DEFAULT 0 AFTER carried_forward_percent");
        }

        // 2. Tier Transitions audit table
        if ($wpdb->get_var("SHOW TABLES LIKE '$transitionsTable'") !== $transitionsTable) {
            $sql = "CREATE TABLE $transitionsTable (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                ticket_id bigint(20) unsigned NOT NULL,
                from_tier_priority int(11) DEFAULT NULL,
                to_tier_priority int(11) NOT NULL,
                actual_percent_at_transition decimal(5,2) NOT NULL,
                carried_percent decimal(5,2) NOT NULL,
                override_reason text DEFAULT NULL,
                transitioned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY ticket_id (ticket_id),
                KEY transitioned_at (transitioned_at)
            ) $charsetCollate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public function down(): void
    {
        global $wpdb;

        $transitionsTable = $wpdb->prefix . 'pet_sla_clock_tier_transitions';
        $clockTable = $wpdb->prefix . 'pet_sla_clock_state';

        $wpdb->query("DROP TABLE IF EXISTS $transitionsTable");
        $wpdb->query("ALTER TABLE $clockTable DROP COLUMN IF EXISTS active_tier_priority");
        $wpdb->query("ALTER TABLE $clockTable DROP COLUMN IF EXISTS tier_elapsed_business_minutes");
        $wpdb->query("ALTER TABLE $clockTable DROP COLUMN IF EXISTS carried_forward_percent");
        $wpdb->query("ALTER TABLE $clockTable DROP COLUMN IF EXISTS total_transitions");
    }

    public function getDescription(): string
    {
        return 'Add tier tracking to SLA clock state and create tier transitions audit table';
    }
}
