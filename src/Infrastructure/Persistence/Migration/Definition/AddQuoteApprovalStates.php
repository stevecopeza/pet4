<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddQuoteApprovalStates implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_quotes';

        // Widen the state column to accommodate new approval states.
        // The existing CHECK constraint (if any) or application-level validation handles valid values.
        // We also add a rejection_note column for manager feedback.
        $col = $wpdb->get_row(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '{$table}'
             AND COLUMN_NAME = 'rejection_note'"
        );

        if (!$col) {
            $wpdb->query(
                "ALTER TABLE {$table}
                 ADD COLUMN rejection_note text NULL DEFAULT NULL AFTER state,
                 ADD COLUMN submitted_for_approval_at datetime NULL DEFAULT NULL AFTER rejection_note,
                 ADD COLUMN approved_at datetime NULL DEFAULT NULL AFTER submitted_for_approval_at,
                 ADD COLUMN approved_by_user_id bigint unsigned NULL DEFAULT NULL AFTER approved_at"
            );
        }
    }

    public function down(): void
    {
        // Forward-only
    }

    public function getDescription(): string
    {
        return 'Add rejection_note, submitted_for_approval_at, approved_at, approved_by_user_id columns to quotes for manager approval workflow';
    }
}
