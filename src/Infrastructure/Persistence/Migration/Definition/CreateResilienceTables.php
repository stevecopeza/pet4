<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateResilienceTables implements Migration
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

        $runs = $this->wpdb->prefix . 'pet_resilience_analysis_runs';
        $sqlRuns = "CREATE TABLE IF NOT EXISTS $runs (
            id char(36) NOT NULL,
            scope_type varchar(32) NOT NULL,
            scope_id bigint(20) unsigned NOT NULL,
            version_number int(11) NOT NULL,
            status varchar(24) NOT NULL,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            generated_by bigint(20) unsigned DEFAULT NULL,
            summary_json longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_scope_version (scope_type, scope_id, version_number),
            KEY scope_latest (scope_type, scope_id, started_at),
            KEY status_idx (status)
        ) $charsetCollate;";

        $signals = $this->wpdb->prefix . 'pet_resilience_signals';
        $sqlSignals = "CREATE TABLE IF NOT EXISTS $signals (
            id char(36) NOT NULL,
            analysis_run_id char(36) NOT NULL,
            scope_type varchar(32) NOT NULL,
            scope_id bigint(20) unsigned NOT NULL,
            signal_type varchar(64) NOT NULL,
            severity varchar(24) NOT NULL,
            title varchar(255) NOT NULL,
            summary text NOT NULL,
            employee_id bigint(20) unsigned DEFAULT NULL,
            team_id bigint(20) unsigned DEFAULT NULL,
            role_id bigint(20) unsigned DEFAULT NULL,
            source_entity_type varchar(64) DEFAULT NULL,
            source_entity_id varchar(64) DEFAULT NULL,
            status varchar(24) NOT NULL,
            created_at datetime NOT NULL,
            resolved_at datetime DEFAULT NULL,
            metadata_json longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY run_idx (analysis_run_id),
            KEY scope_status (scope_type, scope_id, status),
            KEY severity_idx (severity),
            KEY type_idx (signal_type)
        ) $charsetCollate;";

        dbDelta($sqlRuns);
        dbDelta($sqlSignals);
    }

    public function getDescription(): string
    {
        return 'Create resilience analysis runs and signals tables';
    }
}

