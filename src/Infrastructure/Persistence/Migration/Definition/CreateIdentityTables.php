<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateIdentityTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // Employees Table
        $employeesTable = $this->wpdb->prefix . 'pet_employees';
        $sqlEmployees = "CREATE TABLE IF NOT EXISTS $employeesTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) UNSIGNED NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY wp_user_id (wp_user_id),
            KEY email (email)
        ) $charsetCollate;";

        // Customers Table
        $customersTable = $this->wpdb->prefix . 'pet_customers';
        $sqlCustomers = "CREATE TABLE IF NOT EXISTS $customersTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            contact_email varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY contact_email (contact_email)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlEmployees);
        dbDelta($sqlCustomers);
    }

    public function getDescription(): string
    {
        return 'Create initial identity tables: employees and customers.';
    }
}
