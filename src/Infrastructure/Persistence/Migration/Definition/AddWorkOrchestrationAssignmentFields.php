<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddWorkOrchestrationAssignmentFields implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $workItemsTable = $this->wpdb->prefix . 'pet_work_items';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$workItemsTable'") !== $workItemsTable) {
            return;
        }

        $columns = $this->wpdb->get_col("DESCRIBE $workItemsTable", 0);

        if (!in_array('assigned_team_id', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $workItemsTable ADD COLUMN assigned_team_id CHAR(36) NULL AFTER department_id");
        }

        if (!in_array('assignment_mode', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $workItemsTable ADD COLUMN assignment_mode VARCHAR(20) NULL AFTER assigned_team_id");
            $this->wpdb->query("ALTER TABLE $workItemsTable ADD KEY idx_assignment_mode (assignment_mode)");
        }

        if (!in_array('queue_key', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $workItemsTable ADD COLUMN queue_key VARCHAR(255) NULL AFTER assignment_mode");
            $this->wpdb->query("ALTER TABLE $workItemsTable ADD KEY idx_queue_key (queue_key)");
        }

        if (!in_array('routing_reason', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $workItemsTable ADD COLUMN routing_reason TEXT NULL AFTER queue_key");
        }

        $queuesTable = $this->wpdb->prefix . 'pet_department_queues';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$queuesTable'") !== $queuesTable) {
            return;
        }

        $queueColumns = $this->wpdb->get_col("DESCRIBE $queuesTable", 0);
        if (!in_array('assigned_team_id', $queueColumns, true)) {
            $this->wpdb->query("ALTER TABLE $queuesTable ADD COLUMN assigned_team_id CHAR(36) NULL AFTER department_id");
            $this->wpdb->query("ALTER TABLE $queuesTable ADD KEY idx_assigned_team_unassigned (assigned_team_id, assigned_user_id)");
        }
    }

    public function getDescription(): string
    {
        return 'Add assigned_team_id, assignment_mode, queue_key, and routing_reason to work orchestration tables.';
    }
}

