<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreatePulsewayIntegrationTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $this->createFeatureFlags();
        $this->createIntegrationsTable($charsetCollate);
        $this->createExternalNotificationsTable($charsetCollate);
        $this->createExternalAssetsTable($charsetCollate);
        $this->createOrgMappingsTable($charsetCollate);
        $this->createTicketRulesTable($charsetCollate);
    }

    private function createFeatureFlags(): void
    {
        $table = $this->wpdb->prefix . 'pet_settings';
        $now = date('Y-m-d H:i:s');

        $flags = [
            'pet_pulseway_enabled' => [
                'value' => 'false',
                'description' => 'Master switch for Pulseway RMM integration (polling, ingestion, ticket creation)',
            ],
            'pet_pulseway_ticket_creation_enabled' => [
                'value' => 'false',
                'description' => 'Enable automatic ticket creation from Pulseway notifications (requires pet_pulseway_enabled)',
            ],
        ];

        foreach ($flags as $key => $data) {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT setting_key FROM $table WHERE setting_key = %s", $key)
            );

            if (!$exists) {
                $this->wpdb->insert($table, [
                    'setting_key' => $key,
                    'setting_value' => $data['value'],
                    'setting_type' => 'boolean',
                    'description' => $data['description'],
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function createIntegrationsTable(string $charsetCollate): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_integrations';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid char(36) NOT NULL,
            label varchar(128) NOT NULL,
            api_base_url varchar(255) NOT NULL DEFAULT 'https://api.pulseway.com/v3/',
            token_id_encrypted text NOT NULL,
            token_secret_encrypted text NOT NULL,
            poll_interval_seconds int NOT NULL DEFAULT 300,
            last_poll_at datetime DEFAULT NULL,
            last_poll_cursor text DEFAULT NULL,
            last_success_at datetime DEFAULT NULL,
            last_error_at datetime DEFAULT NULL,
            last_error_message text DEFAULT NULL,
            consecutive_failures int NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY is_active (is_active),
            KEY archived_at (archived_at)
        ) $charsetCollate;";
        dbDelta($sql);
    }

    private function createExternalNotificationsTable(string $charsetCollate): void
    {
        $table = $this->wpdb->prefix . 'pet_external_notifications';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id bigint(20) unsigned NOT NULL,
            external_system varchar(32) NOT NULL DEFAULT 'pulseway',
            external_notification_id varchar(128) DEFAULT NULL,
            dedupe_key varchar(128) NOT NULL,
            device_external_id varchar(128) DEFAULT NULL,
            severity varchar(32) DEFAULT NULL,
            category varchar(64) DEFAULT NULL,
            title varchar(512) NOT NULL,
            message text DEFAULT NULL,
            occurred_at datetime DEFAULT NULL,
            received_at datetime NOT NULL,
            raw_payload_json longtext DEFAULT NULL,
            routing_status varchar(32) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dedupe (external_system, dedupe_key),
            KEY integration_id (integration_id),
            KEY routing_status (routing_status),
            KEY device_external_id (device_external_id),
            KEY received_at (received_at),
            KEY severity (severity)
        ) $charsetCollate;";
        dbDelta($sql);
    }

    private function createExternalAssetsTable(string $charsetCollate): void
    {
        $table = $this->wpdb->prefix . 'pet_external_assets';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id bigint(20) unsigned NOT NULL,
            external_system varchar(32) NOT NULL DEFAULT 'pulseway',
            external_asset_id varchar(128) NOT NULL,
            external_org_id varchar(128) DEFAULT NULL,
            external_site_id varchar(128) DEFAULT NULL,
            external_group_id varchar(128) DEFAULT NULL,
            display_name varchar(255) DEFAULT NULL,
            platform varchar(64) DEFAULT NULL,
            status varchar(32) DEFAULT NULL,
            last_seen_at datetime DEFAULT NULL,
            raw_snapshot_json longtext DEFAULT NULL,
            snapshot_updated_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_asset (integration_id, external_system, external_asset_id),
            KEY external_system (external_system),
            KEY status (status)
        ) $charsetCollate;";
        dbDelta($sql);
    }

    private function createOrgMappingsTable(string $charsetCollate): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_org_mappings';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id bigint(20) unsigned NOT NULL,
            pulseway_org_id varchar(128) DEFAULT NULL,
            pulseway_site_id varchar(128) DEFAULT NULL,
            pulseway_group_id varchar(128) DEFAULT NULL,
            pet_customer_id bigint(20) unsigned DEFAULT NULL,
            pet_site_id bigint(20) unsigned DEFAULT NULL,
            pet_team_id bigint(20) unsigned DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_mapping (integration_id, pulseway_org_id, pulseway_site_id, pulseway_group_id),
            KEY pet_customer_id (pet_customer_id),
            KEY is_active (is_active)
        ) $charsetCollate;";
        dbDelta($sql);
    }

    private function createTicketRulesTable(string $charsetCollate): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_ticket_rules';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id bigint(20) unsigned NOT NULL,
            rule_name varchar(128) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            match_severity varchar(255) DEFAULT NULL,
            match_category varchar(255) DEFAULT NULL,
            match_pulseway_org_id varchar(128) DEFAULT NULL,
            match_pulseway_site_id varchar(128) DEFAULT NULL,
            match_pulseway_group_id varchar(128) DEFAULT NULL,
            output_ticket_kind varchar(50) NOT NULL DEFAULT 'incident',
            output_priority varchar(32) NOT NULL DEFAULT 'medium',
            output_queue_id varchar(64) DEFAULT NULL,
            output_owner_user_id varchar(64) DEFAULT NULL,
            output_billing_context_type varchar(32) NOT NULL DEFAULT 'adhoc',
            output_tags_json text DEFAULT NULL,
            dedupe_window_minutes int NOT NULL DEFAULT 60,
            quiet_hours_start time DEFAULT NULL,
            quiet_hours_end time DEFAULT NULL,
            sort_order int NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY integration_id (integration_id),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charsetCollate;";
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create Pulseway RMM integration tables and feature flags';
    }
}
