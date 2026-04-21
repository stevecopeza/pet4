<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTicketProvisioningSourceColumns implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_tickets';

        $this->addColumnIfNotExists($table, 'source_type', 'VARCHAR(64) NULL');
        $this->addColumnIfNotExists($table, 'source_component_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfNotExists($table, 'parent_ticket_key', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');

        if ($this->columnExists($table, 'parent_ticket_id') && $this->columnExists($table, 'parent_ticket_key')) {
            $this->wpdb->query("UPDATE $table SET parent_ticket_key = COALESCE(parent_ticket_id, 0)");
        }

        $this->addIndexIfNotExists($table, 'idx_source_component_id', 'source_component_id');
        $this->addUniqueIndexIfNotExists(
            $table,
            'uq_ticket_project_source_parent',
            'project_id, source_component_id, parent_ticket_id'
        );
        $this->addUniqueIndexIfNotExists(
            $table,
            'uq_ticket_project_source_parent_key',
            'project_id, source_component_id, parent_ticket_key'
        );
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE '$column'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD $column $definition");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE '$column'");
        return !empty($row);
    }

    private function addIndexIfNotExists(string $table, string $indexName, string $columnName): void
    {
        $row = $this->wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD INDEX $indexName ($columnName)");
        }
    }

    private function addUniqueIndexIfNotExists(string $table, string $indexName, string $columns): void
    {
        $row = $this->wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD UNIQUE INDEX $indexName ($columns)");
        }
    }

    public function getDescription(): string
    {
        return 'Add source provisioning columns and null-safe parent key uniqueness for quote component ticket idempotency.';
    }
}
