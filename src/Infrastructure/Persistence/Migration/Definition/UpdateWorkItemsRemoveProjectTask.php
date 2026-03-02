<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateWorkItemsRemoveProjectTask implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_work_items';

        $row = $this->wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'source_type'");
        if ($row && isset($row->Type) && str_contains($row->Type, 'project_' . 'task')) {
            $legacyType = 'project_' . 'task';
            $this->wpdb->query(
                $this->wpdb->prepare("DELETE FROM $table WHERE source_type = %s", $legacyType)
            );

            $this->wpdb->query(
                "ALTER TABLE $table MODIFY COLUMN source_type ENUM('ticket', 'escalation', 'admin') NOT NULL"
            );
        }
    }

    public function getDescription(): string
    {
        return 'Remove legacy project tasks from work item source_type.';
    }
}
