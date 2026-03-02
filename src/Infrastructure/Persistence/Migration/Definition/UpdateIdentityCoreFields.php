<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateIdentityCoreFields implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        // 1. Customers Table
        $customersTable = $this->wpdb->prefix . 'pet_customers';
        
        // Check and add legal_name
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $customersTable LIKE 'legal_name'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $customersTable ADD COLUMN legal_name varchar(255) DEFAULT NULL AFTER name");
        }

        // Check and add status to Customers
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $customersTable LIKE 'status'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $customersTable ADD COLUMN status varchar(20) NOT NULL DEFAULT 'active' AFTER contact_email");
        }

        // 2. Sites Table
        $sitesTable = $this->wpdb->prefix . 'pet_sites';

        // Check and add status to Sites
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $sitesTable LIKE 'status'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $sitesTable ADD COLUMN status varchar(20) NOT NULL DEFAULT 'active' AFTER country");
        }
    }

    public function getDescription(): string
    {
        return 'Add core fields (legal_name, status) to customers and sites tables.';
    }
}
