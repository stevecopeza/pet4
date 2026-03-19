<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateAdvisoryReportTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'pet_advisory_reports';

        $sql = "CREATE TABLE $table (
            id char(36) NOT NULL,
            report_type varchar(50) NOT NULL,
            scope_type varchar(50) NOT NULL,
            scope_id bigint(20) unsigned NOT NULL,
            version_number int NOT NULL,
            title varchar(255) NOT NULL,
            summary text NULL,
            status varchar(20) NOT NULL DEFAULT 'GENERATED',
            generated_at datetime NOT NULL,
            generated_by bigint(20) unsigned NULL,
            content_json longtext NOT NULL,
            source_snapshot_metadata_json longtext NULL,
            PRIMARY KEY  (id),
            KEY scope_lookup (scope_type, scope_id),
            KEY report_lookup (report_type, scope_type, scope_id),
            KEY version_lookup (report_type, scope_type, scope_id, version_number),
            KEY generated_at (generated_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create advisory reports table (versioned, additive).';
    }
}
