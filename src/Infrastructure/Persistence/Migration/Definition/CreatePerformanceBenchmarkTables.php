<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreatePerformanceBenchmarkTables implements Migration
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

        $runsTable = $this->wpdb->prefix . 'pet_performance_runs';
        $sqlRuns = "CREATE TABLE IF NOT EXISTS $runsTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            run_type varchar(64) NOT NULL,
            status varchar(64) NOT NULL,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            duration_ms bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status_idx (status),
            KEY started_at_idx (started_at),
            KEY completed_at_idx (completed_at)
        ) $charsetCollate;";

        $resultsTable = $this->wpdb->prefix . 'pet_performance_results';
        $sqlResults = "CREATE TABLE IF NOT EXISTS $resultsTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            run_id bigint(20) unsigned NOT NULL,
            metric_key varchar(191) NOT NULL,
            metric_value longtext NOT NULL,
            context_json longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY run_id_idx (run_id),
            KEY metric_key_idx (metric_key),
            KEY run_metric_idx (run_id, metric_key)
        ) $charsetCollate;";

        dbDelta($sqlRuns);
        dbDelta($sqlResults);
    }

    public function getDescription(): string
    {
        return 'Create performance benchmark runs and results tables';
    }
}

