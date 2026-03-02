<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

/**
 * C1-M4: Add ticket_id to wp_pet_tasks as a bridge column.
 * Enables existing tasks to reference their corresponding ticket
 * as the system transitions to ticket-as-universal-work-unit.
 */
class AddTicketIdToTasks implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_tasks';

        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'ticket_id'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD ticket_id BIGINT UNSIGNED NULL");
            $this->wpdb->query("ALTER TABLE $table ADD INDEX idx_ticket_id (ticket_id)");
        }
    }

    public function getDescription(): string
    {
        return 'Add ticket_id bridge column to tasks table for ticket backbone transition.';
    }
}
