<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateAdvisoryTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table_signals = $this->wpdb->prefix . 'pet_advisory_signals';

        $sql = "CREATE TABLE $table_signals (
            id char(36) NOT NULL,
            work_item_id char(36) NOT NULL,
            signal_type varchar(50) NOT NULL,
            severity varchar(20) NOT NULL,
            message text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY work_item_id (work_item_id),
            KEY signal_type (signal_type),
            KEY severity (severity)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create Advisory Signal tables';
    }
}
