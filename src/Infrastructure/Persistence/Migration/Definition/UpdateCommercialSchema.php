<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateCommercialSchema implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $quotesTable = $this->wpdb->prefix . 'pet_quotes';
        
        // Check and add total_value
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $quotesTable LIKE 'total_value'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $quotesTable ADD COLUMN total_value decimal(10, 2) NOT NULL DEFAULT 0.00 AFTER version");
        }

        // Check and add currency
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $quotesTable LIKE 'currency'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $quotesTable ADD COLUMN currency varchar(3) NOT NULL DEFAULT 'USD' AFTER total_value");
        }

        // Check and add accepted_at
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $quotesTable LIKE 'accepted_at'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $quotesTable ADD COLUMN accepted_at datetime DEFAULT NULL AFTER currency");
        }

        // Check and add malleable_data
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $quotesTable LIKE 'malleable_data'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $quotesTable ADD COLUMN malleable_data longtext DEFAULT NULL AFTER accepted_at");
        }
    }

    public function getDescription(): string
    {
        return 'Add total_value, currency, accepted_at, and malleable_data to quotes table.';
    }
}
