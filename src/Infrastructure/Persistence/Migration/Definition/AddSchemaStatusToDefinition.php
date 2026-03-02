<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddSchemaStatusToDefinition implements Migration
{
    public function getVersion(): string
    {
        return '1.0.3';
    }

    public function getDescription(): string
    {
        return 'Add status, published_at, published_by to schema definitions table.';
    }

    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_schema_definitions';
        $charsetCollate = $wpdb->get_charset_collate();

        // We use dbDelta to add columns safely.
        // status: enum(draft, active, historical)
        // published_at: datetime when status became active
        // published_by: employee ID who published it

        $sql = "CREATE TABLE $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            version int(10) UNSIGNED NOT NULL,
            schema_json longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            published_at datetime DEFAULT NULL,
            published_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL,
            created_by_employee_id bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY entity_version (entity_type, version)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Backfill existing rows:
        // 1. If status is empty (newly added column), set defaults.
        // Strategy: 
        // - Find the latest version for each entity_type -> Active
        // - All others -> Historical
        
        // This is a bit complex for simple SQL updates if we want to be precise,
        // but for now, let's just default everything to 'historical' first, 
        // then update the latest one to 'active'.
        
        // However, dbDelta doesn't run logic, just schema. 
        // We'll run a direct query to backfill defaults if they are empty/default.
        // Since we set DEFAULT 'draft', existing rows might get 'draft' or empty string depending on MySQL version/strictness with dbDelta.
        // Usually dbDelta adds the column. If strict mode is off, it might be empty string.
        
        // Let's explicitly update any row with empty status or default 'draft' that was created before this migration (effectively all existing rows).
        
        // For simplicity in this migration step, we will mark ALL existing as 'active' if they are the latest, or 'historical'.
        // Actually, let's just mark everything as 'historical' to be safe, and let the user manually activate? 
        // No, that breaks the app.
        
        // Better strategy:
        // Update all to 'historical' first.
        $wpdb->query("UPDATE $table SET status = 'historical' WHERE status = '' OR status = 'draft'");
        
        // Then find max version for each entity and set to 'active'.
        $rows = $wpdb->get_results("SELECT entity_type, MAX(version) as max_ver FROM $table GROUP BY entity_type");
        
        foreach ($rows as $row) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status = 'active', published_at = created_at WHERE entity_type = %s AND version = %d",
                $row->entity_type,
                $row->max_ver
            ));
        }
    }
}
