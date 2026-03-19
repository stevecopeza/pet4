<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateEscalationTables implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $escalationsTable = $wpdb->prefix . 'pet_escalations';
        $transitionsTable = $wpdb->prefix . 'pet_escalation_transitions';

        $sqlEscalations = "CREATE TABLE $escalationsTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            escalation_id char(36) NOT NULL,
            source_entity_type varchar(50) NOT NULL,
            source_entity_id bigint(20) unsigned NOT NULL,
            severity varchar(20) NOT NULL DEFAULT 'MEDIUM',
            status varchar(20) NOT NULL DEFAULT 'OPEN',
            reason text NOT NULL,
            metadata_json longtext NULL,
            created_by bigint(20) unsigned NULL,
            acknowledged_by bigint(20) unsigned NULL,
            resolved_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            acknowledged_at datetime NULL,
            resolved_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY escalation_id (escalation_id),
            KEY source_entity (source_entity_type, source_entity_id),
            KEY status (status),
            KEY severity (severity),
            KEY created_at (created_at)
        ) $charsetCollate;";

        $sqlTransitions = "CREATE TABLE $transitionsTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            escalation_id bigint(20) unsigned NOT NULL,
            from_status varchar(20) NOT NULL,
            to_status varchar(20) NOT NULL,
            transitioned_by bigint(20) unsigned NULL,
            transitioned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY escalation_id (escalation_id),
            KEY transitioned_at (transitioned_at)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlEscalations);
        dbDelta($sqlTransitions);
    }

    public function down(): void
    {
        // No-op for forward-only migrations
    }

    public function getDescription(): string
    {
        return 'Create escalation domain tables (escalations and transitions)';
    }
}
