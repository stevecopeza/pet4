<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateWorkOrchestrationTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Work Items Table
        $work_items_sql = "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}pet_work_items (
            id CHAR(36) NOT NULL,
            source_type ENUM('ticket', 'escalation', 'admin') NOT NULL,
            source_id CHAR(36) NOT NULL,
            assigned_user_id CHAR(36) NULL,
            department_id CHAR(36) NOT NULL,
            sla_snapshot_id CHAR(36) NULL,
            sla_time_remaining_minutes INT NULL,
            priority_score DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            scheduled_start_utc DATETIME NULL,
            scheduled_due_utc DATETIME NULL,
            capacity_allocation_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
            status ENUM('active', 'waiting', 'completed') NOT NULL DEFAULT 'active',
            escalation_level INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_source (source_type, source_id),
            KEY idx_priority (priority_score),
            KEY idx_assigned_user (assigned_user_id),
            KEY idx_department (department_id)
        ) $charset_collate;";

        // Department Queues Table
        $queues_sql = "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}pet_department_queues (
            id CHAR(36) NOT NULL,
            department_id CHAR(36) NOT NULL,
            work_item_id CHAR(36) NOT NULL,
            assigned_user_id CHAR(36) NULL,
            entered_queue_at DATETIME NOT NULL,
            picked_up_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_department_unassigned (department_id, assigned_user_id),
            CONSTRAINT fk_queue_work_item FOREIGN KEY (work_item_id) REFERENCES {$this->wpdb->prefix}pet_work_items(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($work_items_sql);
        dbDelta($queues_sql);
    }

    public function getDescription(): string
    {
        return 'Create Work Orchestration tables (work_items and department_queues)';
    }
}
