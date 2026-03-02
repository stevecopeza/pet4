<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateSlaTables implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $slasTable = $wpdb->prefix . 'pet_slas';
        $escalationRulesTable = $wpdb->prefix . 'pet_sla_escalation_rules';
        $snapshotsTable = $wpdb->prefix . 'pet_contract_sla_snapshots';
        $calendarsTable = $wpdb->prefix . 'pet_calendars'; // FK reference

        // 1. SLAs Table
        // Represents the "Template" or "Definition" of an SLA (e.g. "Gold Support v1")
        $sqlSlas = "CREATE TABLE $slasTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid char(36) NOT NULL,
            name varchar(255) NOT NULL,
            description text NULL,
            status varchar(50) NOT NULL DEFAULT 'draft', -- draft, published, deprecated
            version_number int(11) NOT NULL DEFAULT 1,
            calendar_id bigint(20) unsigned NOT NULL,
            response_target_minutes int(11) NOT NULL,
            resolution_target_minutes int(11) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY calendar_id (calendar_id),
            FOREIGN KEY (calendar_id) REFERENCES $calendarsTable(id)
        ) $charsetCollate;";

        // 2. Escalation Rules Table
        // Rules for when to notify/escalate based on % of time elapsed
        $sqlRules = "CREATE TABLE $escalationRulesTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sla_id bigint(20) unsigned NOT NULL,
            threshold_percent int(11) NOT NULL, -- e.g. 75 for 75%
            action varchar(50) NOT NULL DEFAULT 'notify_manager',
            PRIMARY KEY  (id),
            KEY sla_id (sla_id),
            FOREIGN KEY (sla_id) REFERENCES $slasTable(id) ON DELETE CASCADE
        ) $charsetCollate;";

        // 3. Contract SLA Snapshots Table
        // Binds a specific version of an SLA + Calendar to a Contract/Project
        // This is immutable: if the 'Gold' SLA changes later, this snapshot preserves the sold terms.
        $sqlSnapshots = "CREATE TABLE $snapshotsTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid char(36) NOT NULL,
            project_id bigint(20) unsigned NULL, -- Links to the Project (Contract), nullable for ad-hoc/ticket-specific
            sla_original_id bigint(20) unsigned NOT NULL, -- Reference to origin SLA
            sla_version_at_binding int(11) NOT NULL,
            sla_name_at_binding varchar(255) NOT NULL,
            response_target_minutes int(11) NOT NULL,
            resolution_target_minutes int(11) NOT NULL,
            calendar_snapshot_json longtext NOT NULL, -- Full JSON dump of the calendar at binding time
            bound_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY project_id (project_id)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlSlas);
        dbDelta($sqlRules);
        dbDelta($sqlSnapshots);
    }

    public function down(): void
    {
        // No-op for forward-only migrations
    }

    public function getDescription(): string
    {
        return 'Create SLA definition, escalation rules, and contract snapshot tables';
    }
}
