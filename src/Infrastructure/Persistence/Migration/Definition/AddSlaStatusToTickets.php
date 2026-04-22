<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

class AddSlaStatusToTickets
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_tickets';

        $column = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'sla_status'",
                DB_NAME,
                $table
            )
        );

        if (empty($column)) {
            $wpdb->query(
                "ALTER TABLE `{$table}`
                 ADD COLUMN `sla_status` VARCHAR(20) NULL DEFAULT NULL
                 AFTER `resolution_due_at`"
            );
        }
    }
}
