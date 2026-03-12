<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddEscalationDedupeKey implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $escalationsTable = $this->wpdb->prefix . 'pet_escalations';
        $transitionsTable = $this->wpdb->prefix . 'pet_escalation_transitions';

        // Add open_dedupe_key column if it doesn't exist
        $columns = $this->wpdb->get_col("DESCRIBE $escalationsTable", 0);
        if (!in_array('open_dedupe_key', $columns, true)) {
            $this->wpdb->query(
                "ALTER TABLE $escalationsTable ADD COLUMN open_dedupe_key VARCHAR(64) NULL AFTER metadata_json"
            );
            $this->wpdb->query(
                "ALTER TABLE $escalationsTable ADD UNIQUE KEY open_dedupe_key (open_dedupe_key)"
            );
        }

        // Allow NULL in from_status for the initial NULL → OPEN transition
        $this->wpdb->query(
            "ALTER TABLE $transitionsTable MODIFY from_status VARCHAR(20) NULL"
        );

        // Add reason column to transitions for audit trail
        $transColumns = $this->wpdb->get_col("DESCRIBE $transitionsTable", 0);
        if (!in_array('reason', $transColumns, true)) {
            $this->wpdb->query(
                "ALTER TABLE $transitionsTable ADD COLUMN reason TEXT NULL AFTER transitioned_by"
            );
        }
    }

    public function getDescription(): string
    {
        return 'Add escalation dedupe key, allow NULL from_status in transitions, add transition reason column.';
    }
}
