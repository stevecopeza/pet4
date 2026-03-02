<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateSlaClockStateTable implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_sla_clock_state';
        $charsetCollate = $wpdb->get_charset_collate();

        // Check if table exists to avoid errors on re-run
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) unsigned NOT NULL,
            sla_version_id bigint(20) unsigned NOT NULL,
            warning_at datetime DEFAULT NULL,
            breach_at datetime DEFAULT NULL,
            paused_flag tinyint(1) NOT NULL DEFAULT 0,
            escalation_stage int(11) NOT NULL DEFAULT 0,
            last_evaluated_at datetime DEFAULT NULL,
            last_event_dispatched varchar(50) DEFAULT 'none',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_id (ticket_id),
            KEY breach_at (breach_at),
            KEY last_evaluated_at (last_evaluated_at)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create SLA clock state table for automation loop';
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_sla_clock_state';
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}
