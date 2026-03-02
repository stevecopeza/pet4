<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddMalleableIndexes implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $tables = [
            'pet_customers',
            'pet_sites',
            'pet_contacts',
            'pet_projects',
            'pet_articles',
            'pet_tickets',
            'pet_employees'
        ];

        foreach ($tables as $table) {
            $tableName = $this->wpdb->prefix . $table;
            
            // Check if index exists
            $indexExists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW INDEX FROM $tableName WHERE Key_name = %s",
                    'malleable_schema_version'
                )
            );

            if (!$indexExists) {
                $this->wpdb->query("ALTER TABLE $tableName ADD INDEX malleable_schema_version (malleable_schema_version)");
            }
        }
    }

    public function getDescription(): string
    {
        return 'Add indexes to malleable_schema_version column on all malleable entities.';
    }
}
