<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddMissingCoreFields implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        // 1. Customers
        $customersTable = $this->wpdb->prefix . 'pet_customers';
        $this->addColumnIfNotExists($customersTable, 'legal_name', 'varchar(255) DEFAULT NULL');
        $this->addColumnIfNotExists($customersTable, 'status', "varchar(50) DEFAULT 'active' NOT NULL");

        // 2. Sites
        $sitesTable = $this->wpdb->prefix . 'pet_sites';
        $this->addColumnIfNotExists($sitesTable, 'status', "varchar(50) DEFAULT 'active' NOT NULL");

        // 3. Contacts
        $contactsTable = $this->wpdb->prefix . 'pet_contacts';
        $this->addColumnIfNotExists($contactsTable, 'status', "varchar(50) DEFAULT 'active' NOT NULL");

        // 4. Quotes
        $quotesTable = $this->wpdb->prefix . 'pet_quotes';
        $this->addColumnIfNotExists($quotesTable, 'total_value', 'decimal(15,2) DEFAULT 0.00');
        $this->addColumnIfNotExists($quotesTable, 'currency', 'char(3) DEFAULT NULL');
        $this->addColumnIfNotExists($quotesTable, 'accepted_at', 'datetime DEFAULT NULL');

        // 5. Projects
        $projectsTable = $this->wpdb->prefix . 'pet_projects';
        $this->addColumnIfNotExists($projectsTable, 'sold_value', 'decimal(15,2) DEFAULT 0.00');
        $this->addColumnIfNotExists($projectsTable, 'start_date', 'date DEFAULT NULL');
        $this->addColumnIfNotExists($projectsTable, 'end_date', 'date DEFAULT NULL');

        // 6. Tickets
        $ticketsTable = $this->wpdb->prefix . 'pet_tickets';
        $this->addColumnIfNotExists($ticketsTable, 'site_id', 'bigint(20) UNSIGNED DEFAULT NULL');
        $this->addColumnIfNotExists($ticketsTable, 'sla_id', 'bigint(20) UNSIGNED DEFAULT NULL');
        $this->addColumnIfNotExists($ticketsTable, 'opened_at', 'datetime DEFAULT NULL');
        $this->addColumnIfNotExists($ticketsTable, 'closed_at', 'datetime DEFAULT NULL');

        // Backfill Ticket dates
        $this->wpdb->query("UPDATE $ticketsTable SET opened_at = created_at WHERE opened_at IS NULL");
        // If resolved_at exists, use it for closed_at. (resolved_at check)
        $resolvedCol = $this->wpdb->get_results("SHOW COLUMNS FROM $ticketsTable LIKE 'resolved_at'");
        if (!empty($resolvedCol)) {
            $this->wpdb->query("UPDATE $ticketsTable SET closed_at = resolved_at WHERE closed_at IS NULL AND resolved_at IS NOT NULL");
        }

        // 7. Time Entries
        $timeEntriesTable = $this->wpdb->prefix . 'pet_time_entries';
        $this->addColumnIfNotExists($timeEntriesTable, 'submitted_at', 'datetime DEFAULT NULL');
        
        // Backfill Time Entry dates
        $this->wpdb->query("UPDATE $timeEntriesTable SET submitted_at = created_at WHERE submitted_at IS NULL");

        // 8. Knowledge Articles
        $articlesTable = $this->wpdb->prefix . 'pet_articles';
        $this->addColumnIfNotExists($articlesTable, 'created_by', 'bigint(20) UNSIGNED DEFAULT NULL');

        // 9. Employees
        $employeesTable = $this->wpdb->prefix . 'pet_employees';
        $this->addColumnIfNotExists($employeesTable, 'status', "varchar(50) DEFAULT 'active' NOT NULL");
        $this->addColumnIfNotExists($employeesTable, 'hire_date', 'date DEFAULT NULL');
        $this->addColumnIfNotExists($employeesTable, 'manager_id', 'bigint(20) UNSIGNED DEFAULT NULL');
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE '$column'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    }

    public function getDescription(): string
    {
        return 'Add missing core fields to customers, sites, contacts, quotes, projects, tickets, time entries, articles, and employees.';
    }
}
