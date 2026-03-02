<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTicketCoreFields implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        
        // Add columns if they don't exist
        $this->addColumnIfNotExists($table, 'site_id', 'mediumint(9) DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'sla_id', 'mediumint(9) DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'opened_at', 'datetime DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'closed_at', 'datetime DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'queue_id', 'char(36) DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'owner_user_id', 'char(36) DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'category', 'varchar(100) DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'subcategory', 'varchar(100) DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'intake_source', 'varchar(50) DEFAULT NULL');
        $this->addColumnIfNotExists($table, 'contact_id', 'mediumint(9) DEFAULT NULL');

        // Add indexes
        $this->addIndexIfNotExists($table, 'site_id', 'site_id');
        $this->addIndexIfNotExists($table, 'queue_id', 'queue_id');
        $this->addIndexIfNotExists($table, 'owner_user_id', 'owner_user_id');
        $this->addIndexIfNotExists($table, 'category', 'category');
        $this->addIndexIfNotExists($table, 'subcategory', 'subcategory');
        $this->addIndexIfNotExists($table, 'intake_source', 'intake_source');
        $this->addIndexIfNotExists($table, 'contact_id', 'contact_id');
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE '$column'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD $column $definition");
        }
    }

    private function addIndexIfNotExists(string $table, string $indexName, string $columnName): void
    {
        $row = $this->wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD INDEX $indexName ($columnName)");
        }
    }

    public function getDescription(): string
    {
        return 'Add core fields (site_id, sla_id, opened_at, closed_at, queue/owner and categorisation) to tickets table.';
    }
}
