<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateTimeTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // Time Entries Table
        $timeEntriesTable = $this->wpdb->prefix . 'pet_time_entries';
        $sqlTimeEntries = "CREATE TABLE IF NOT EXISTS $timeEntriesTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime NOT NULL,
            duration_minutes int(11) NOT NULL,
            is_billable tinyint(1) NOT NULL DEFAULT 1,
            description text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY ticket_id (ticket_id),
            KEY start_time (start_time),
            KEY status (status)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlTimeEntries);
    }

    public function getDescription(): string
    {
        return 'Create time tables: time_entries.';
    }
}
