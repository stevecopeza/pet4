<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateEventBackboneTables implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $eventStream = $wpdb->prefix . 'pet_domain_event_stream';
        if ($wpdb->get_var("SHOW TABLES LIKE '$eventStream'") !== $eventStream) {
            $sql = "CREATE TABLE $eventStream (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_uuid char(36) NOT NULL,
                occurred_at datetime NOT NULL,
                recorded_at datetime NOT NULL,
                aggregate_type varchar(64) NOT NULL,
                aggregate_id bigint(20) NOT NULL,
                aggregate_version int(11) NOT NULL,
                event_type varchar(128) NOT NULL,
                event_schema_version int(11) NOT NULL DEFAULT 1,
                actor_type varchar(32) DEFAULT NULL,
                actor_id bigint(20) DEFAULT NULL,
                correlation_id char(36) DEFAULT NULL,
                causation_id char(36) DEFAULT NULL,
                payload_json longtext NOT NULL,
                metadata_json longtext DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY event_uuid (event_uuid),
                KEY occurred_at (occurred_at),
                KEY event_type (event_type),
                KEY aggregate_type (aggregate_type),
                KEY aggregate_id (aggregate_id),
                KEY aggregate_version (aggregate_version),
                KEY correlation_id (correlation_id),
                KEY causation_id (causation_id),
                KEY aggregate_tiv (aggregate_type, aggregate_id, aggregate_version)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        $offsets = $wpdb->prefix . 'pet_projection_offsets';
        if ($wpdb->get_var("SHOW TABLES LIKE '$offsets'") !== $offsets) {
            $sql = "CREATE TABLE $offsets (
                projection_name varchar(128) NOT NULL,
                last_event_id bigint(20) unsigned NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (projection_name)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        $outbox = $wpdb->prefix . 'pet_outbox';
        if ($wpdb->get_var("SHOW TABLES LIKE '$outbox'") !== $outbox) {
            $sql = "CREATE TABLE $outbox (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_id bigint(20) unsigned NOT NULL,
                destination varchar(64) NOT NULL,
                status varchar(16) NOT NULL,
                attempt_count int(11) NOT NULL DEFAULT 0,
                next_attempt_at datetime DEFAULT NULL,
                last_error text DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY event_id (event_id),
                KEY next_attempt_at (next_attempt_at),
                KEY status (status)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }

    public function getDescription(): string
    {
        return 'Create event backbone tables: event stream, projection offsets, outbox';
    }
}
