<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateMalleableSchema implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $tables = [
            'pet_projects',
            'pet_articles',
            'pet_tickets',
            'pet_employees'
        ];

        foreach ($tables as $table) {
            $tableName = $this->wpdb->prefix . $table;
            
            // Check if columns exist before adding
            $row = $this->wpdb->get_results("SHOW COLUMNS FROM $tableName LIKE 'malleable_schema_version'");
            if (empty($row)) {
                $this->wpdb->query("ALTER TABLE $tableName ADD COLUMN malleable_schema_version int(10) UNSIGNED DEFAULT NULL");
            }

            $row = $this->wpdb->get_results("SHOW COLUMNS FROM $tableName LIKE 'malleable_data'");
            if (empty($row)) {
                $this->wpdb->query("ALTER TABLE $tableName ADD COLUMN malleable_data longtext DEFAULT NULL AFTER malleable_schema_version");
            }
        }
    }

    public function getDescription(): string
    {
        return 'Add malleable fields to projects, articles, tickets, and employees.';
    }
}
