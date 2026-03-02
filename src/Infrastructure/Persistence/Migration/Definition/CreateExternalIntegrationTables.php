<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateExternalIntegrationTables implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $mappings = $wpdb->prefix . 'pet_external_mappings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$mappings'") !== $mappings) {
            $sql = "CREATE TABLE $mappings (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `system` varchar(32) NOT NULL,
                entity_type varchar(64) NOT NULL,
                pet_entity_id bigint(20) NOT NULL,
                external_id varchar(128) NOT NULL,
                external_version varchar(64) DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_pet_entity (`system`, entity_type, pet_entity_id),
                UNIQUE KEY uniq_external (`system`, entity_type, external_id)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        $runs = $wpdb->prefix . 'pet_integration_runs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$runs'") !== $runs) {
            $sql = "CREATE TABLE $runs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                `system` varchar(32) NOT NULL,
                direction varchar(16) NOT NULL,
                status varchar(16) NOT NULL,
                started_at datetime NOT NULL,
                finished_at datetime DEFAULT NULL,
                summary_json longtext NULL,
                last_error text NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uuid (uuid),
                KEY system (`system`),
                KEY status (status),
                KEY direction (direction),
                KEY started_at (started_at)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }

    public function getDescription(): string
    {
        return 'Create external mappings and integration runs tables';
    }
}
