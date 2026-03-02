<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateIdentitySchema implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // 1. Schema Definitions Table
        $schemaTable = $this->wpdb->prefix . 'pet_schema_definitions';
        $sqlSchema = "CREATE TABLE IF NOT EXISTS $schemaTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            version int(10) UNSIGNED NOT NULL,
            schema_json longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            created_by_employee_id bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY entity_version (entity_type, version)
        ) $charsetCollate;";

        // 2. Sites Table
        $sitesTable = $this->wpdb->prefix . 'pet_sites';
        $sqlSites = "CREATE TABLE IF NOT EXISTS $sitesTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            address_lines text DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(100) DEFAULT NULL,
            postal_code varchar(20) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            malleable_schema_version int(10) UNSIGNED DEFAULT NULL,
            malleable_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id)
        ) $charsetCollate;";

        // 3. Contacts Table
        $contactsTable = $this->wpdb->prefix . 'pet_contacts';
        $sqlContacts = "CREATE TABLE IF NOT EXISTS $contactsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            site_id bigint(20) UNSIGNED DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            malleable_schema_version int(10) UNSIGNED DEFAULT NULL,
            malleable_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY site_id (site_id),
            KEY email (email)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlSchema);
        dbDelta($sqlSites);
        dbDelta($sqlContacts);

        // 4. Update Customers Table (using ALTER for safety on existing data)
        $customersTable = $this->wpdb->prefix . 'pet_customers';
        
        // Check if columns exist before adding
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $customersTable LIKE 'malleable_schema_version'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $customersTable ADD COLUMN malleable_schema_version int(10) UNSIGNED DEFAULT NULL AFTER contact_email");
        }

        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $customersTable LIKE 'malleable_data'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $customersTable ADD COLUMN malleable_data longtext DEFAULT NULL AFTER malleable_schema_version");
        }
    }

    public function getDescription(): string
    {
        return 'Create sites, contacts, and schema definition tables; add malleable fields to customers.';
    }
}
