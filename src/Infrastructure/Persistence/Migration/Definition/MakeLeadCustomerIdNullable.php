<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class MakeLeadCustomerIdNullable implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_leads';

        // Check current nullability — only alter if still NOT NULL
        $col = $wpdb->get_row(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '{$table}'
             AND COLUMN_NAME = 'customer_id'"
        );

        if ($col && $col->IS_NULLABLE === 'NO') {
            $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN customer_id mediumint(9) NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        // Forward-only — reverting to NOT NULL risks data loss on rows with no customer
    }

    public function getDescription(): string
    {
        return 'Make leads.customer_id nullable so leads can exist before a customer record is created';
    }
}
