<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

/**
 * C1-M7: Add sold-ticket architecture columns to wp_pet_tickets.
 * Supports the single-ticket model (Architecture Decision Record v1).
 * All columns are additive — no destructive changes.
 */
class AddTicketSoldArchitectureColumns implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_tickets';

        // Baseline lock — 1 for all tickets created from accepted quotes
        $this->addColumnIfNotExists($table, 'is_baseline_locked', 'TINYINT(1) NOT NULL DEFAULT 0');

        // Change order linkage — points to the original sold ticket
        $this->addColumnIfNotExists($table, 'change_order_source_ticket_id', 'BIGINT UNSIGNED NULL');

        // Sold commercial value in cents (immutable after creation)
        $this->addColumnIfNotExists($table, 'sold_value_cents', 'BIGINT NULL');

        // Indexes
        $this->addIndexIfNotExists($table, 'idx_is_baseline_locked', 'is_baseline_locked');
        $this->addIndexIfNotExists($table, 'idx_change_order_source_ticket_id', 'change_order_source_ticket_id');
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
        return 'Add sold-ticket architecture columns: is_baseline_locked, change_order_source_ticket_id, sold_value_cents.';
    }
}
