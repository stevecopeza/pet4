<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

/**
 * C1-M1: Extend wp_pet_tickets to support cross-system contexts.
 * Makes Ticket the universal work unit (support, project, internal).
 * All columns are additive — no destructive changes.
 */
class AddTicketBackboneColumns implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_tickets';

        // Identity / container
        $this->addColumnIfNotExists($table, 'primary_container', "VARCHAR(20) NOT NULL DEFAULT 'support'");
        $this->addColumnIfNotExists($table, 'project_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'quote_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'phase_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'parent_ticket_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'root_ticket_id', 'BIGINT UNSIGNED NULL');

        // Classification
        $this->addColumnIfNotExists($table, 'ticket_kind', "VARCHAR(50) NOT NULL DEFAULT 'work'");
        $this->addColumnIfNotExists($table, 'department_id_ext', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'required_role_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'skill_level', 'VARCHAR(50) NULL');

        // Commercial context
        $this->addColumnIfNotExists($table, 'billing_context_type', "VARCHAR(20) NOT NULL DEFAULT 'adhoc'");
        $this->addColumnIfNotExists($table, 'agreement_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'rate_plan_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'is_billable_default', 'TINYINT(1) NOT NULL DEFAULT 1');

        // Sold baseline / estimation
        $this->addColumnIfNotExists($table, 'sold_minutes', 'INT NULL');
        $this->addColumnIfNotExists($table, 'estimated_minutes', 'INT NULL');
        $this->addColumnIfNotExists($table, 'remaining_minutes', 'INT NULL');

        // Leaf-only enforcement
        $this->addColumnIfNotExists($table, 'is_rollup', 'TINYINT(1) NOT NULL DEFAULT 0');

        // Lifecycle authority
        $this->addColumnIfNotExists($table, 'lifecycle_owner', "VARCHAR(20) NOT NULL DEFAULT 'support'");

        // Indexes for common queries
        $this->addIndexIfNotExists($table, 'idx_primary_container', 'primary_container');
        $this->addIndexIfNotExists($table, 'idx_project_id', 'project_id');
        $this->addIndexIfNotExists($table, 'idx_parent_ticket_id', 'parent_ticket_id');
        $this->addIndexIfNotExists($table, 'idx_root_ticket_id', 'root_ticket_id');
        $this->addIndexIfNotExists($table, 'idx_billing_context_type', 'billing_context_type');
        $this->addIndexIfNotExists($table, 'idx_is_rollup', 'is_rollup');
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE '$column'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD $column $definition");
        }
    }

    private function addIndexIfNotExists(string $table, string $indexName, string $columnName): void
    {
        $row = $this->wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD INDEX $indexName ($columnName)");
        }
    }

    public function getDescription(): string
    {
        return 'Add ticket backbone columns: primary_container, WBS, classification, commercial context, sold baseline, leaf enforcement, lifecycle owner.';
    }
}
