<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTicketSlaFields implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_tickets';
        
        $sql = "ALTER TABLE $table 
            ADD COLUMN sla_snapshot_id bigint(20) DEFAULT NULL,
            ADD COLUMN response_due_at datetime DEFAULT NULL,
            ADD COLUMN resolution_due_at datetime DEFAULT NULL,
            ADD COLUMN responded_at datetime DEFAULT NULL,
            ADD INDEX idx_sla_snapshot (sla_snapshot_id),
            ADD INDEX idx_response_due (response_due_at),
            ADD INDEX idx_resolution_due (resolution_due_at)
        ";

        // Check if columns exist to avoid errors on repeated runs (although MigrationRunner should handle this)
        // For robustness in dbDelta/raw SQL:
        $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'sla_snapshot_id'");
        if (empty($row)) {
            $wpdb->query($sql);
        }
    }

    public function down(): void
    {
        // No down migration
    }

    public function getDescription(): string
    {
        return 'Add SLA snapshot and due date fields to tickets table';
    }
}
