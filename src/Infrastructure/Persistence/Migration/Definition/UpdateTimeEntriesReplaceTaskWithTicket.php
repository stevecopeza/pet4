<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateTimeEntriesReplaceTaskWithTicket implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_time_entries';

        $columns = $this->wpdb->get_col("SHOW COLUMNS FROM $table LIKE 'ticket_id'");
        if (empty($columns)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN ticket_id bigint(20) UNSIGNED NOT NULL AFTER employee_id");
            $this->wpdb->query("ALTER TABLE $table ADD KEY ticket_id (ticket_id)");
        }

        $legacyTaskColumnName = 'task_' . 'id';
        $taskColumn = $this->wpdb->get_col(
            $this->wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", $legacyTaskColumnName)
        );
        if (!empty($taskColumn)) {
            $this->wpdb->query(
                "UPDATE $table SET ticket_id = {$legacyTaskColumnName} WHERE ticket_id IS NULL OR ticket_id = 0"
            );
            $this->wpdb->query("ALTER TABLE $table DROP COLUMN {$legacyTaskColumnName}");
        }
    }

    public function getDescription(): string
    {
        return 'Replace legacy time entry task column with ticket_id and drop it.';
    }
}
