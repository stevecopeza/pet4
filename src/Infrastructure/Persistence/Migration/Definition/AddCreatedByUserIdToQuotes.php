<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

/**
 * Adds created_by_user_id (WP user ID) to wp_pet_quotes.
 *
 * This field records which WordPress user created a quote and is used by the
 * approval handler to permit self-approval: a sales person can approve their
 * own quote without needing a team manager to act as a separate approver.
 *
 * Existing quotes will have NULL for this field — they are grandfathered and
 * will fall back to the manager-only approval path until re-created.
 */
class AddCreatedByUserIdToQuotes implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_quotes';

        $columnExists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = %s
               AND COLUMN_NAME  = 'created_by_user_id'",
            $table
        ));

        if (!$columnExists) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL");
        }
    }

    public function getDescription(): string
    {
        return 'Add created_by_user_id to pet_quotes for self-approval support';
    }
}
