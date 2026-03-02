<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

/**
 * Add nullable lead_id FK to quotes table so a quote can be linked back
 * to the lead it was converted from.
 */
class AddLeadIdToQuotes implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_quotes';

        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'lead_id'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD lead_id BIGINT UNSIGNED NULL AFTER customer_id");
            $this->wpdb->query("ALTER TABLE $table ADD INDEX idx_lead_id (lead_id)");
        }
    }

    public function getDescription(): string
    {
        return 'Add nullable lead_id column to quotes table for lead-to-quote conversion tracking.';
    }
}
