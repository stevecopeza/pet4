<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

/**
 * Adds a unique constraint on (event_id, destination) in wp_pet_outbox.
 *
 * Without this, duplicate outbox rows for the same event+destination can be
 * inserted (e.g. by concurrent EventBus dispatches), causing the same external
 * event to be dispatched more than once. The unique constraint is the database-
 * level backstop — even if the application enqueues the same event twice, the
 * second INSERT is rejected.
 *
 * Deduplication step: in case duplicates already exist we remove the higher-id
 * row before adding the constraint.
 */
class AddOutboxUniqueConstraint implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_outbox';

        // Skip if table does not exist.
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }

        // Remove any existing duplicates on (event_id, destination) by keeping
        // only the lowest-id row for each pair, so the constraint can be applied.
        $wpdb->query("
            DELETE o1 FROM {$table} o1
            INNER JOIN {$table} o2
                ON  o1.event_id    = o2.event_id
                AND o1.destination = o2.destination
                AND o1.id          > o2.id
        ");

        // Add the unique constraint only if it doesn't already exist.
        $constraintExists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = %s
               AND CONSTRAINT_NAME = 'uq_outbox_event_dest'",
            $table
        ));

        if (!$constraintExists) {
            $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uq_outbox_event_dest (event_id, destination)");
        }
    }

    public function getDescription(): string
    {
        return 'Add unique constraint on (event_id, destination) in pet_outbox to prevent duplicate dispatch';
    }
}
