<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateWorkItemsEnforceTicketOnlyDelivery implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }

        $row = $this->wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'source_type'");
        if (!$row || !isset($row->Type)) {
            return;
        }

        if (str_contains((string)$row->Type, 'project_' . 'task')) {
            $this->wpdb->query(
                $this->wpdb->prepare("DELETE FROM $table WHERE source_type = %s", 'project_task')
            );
            $this->wpdb->query(
                "ALTER TABLE $table MODIFY COLUMN source_type ENUM('ticket', 'escalation', 'admin') NOT NULL"
            );
        }
    }

    public function getDescription(): string
    {
        return 'Enforce tickets-only delivery projection by removing project_task as a work item source type.';
    }
}
