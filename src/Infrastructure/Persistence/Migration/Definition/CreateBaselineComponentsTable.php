<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateBaselineComponentsTable implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_baseline_components';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $charsetCollate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            baseline_id bigint(20) NOT NULL,
            component_type varchar(50) NOT NULL,
            description text NOT NULL,
            sell_value decimal(10, 2) NOT NULL DEFAULT 0.00,
            internal_cost decimal(10, 2) NOT NULL DEFAULT 0.00,
            component_data longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY baseline_id (baseline_id)
        ) $charsetCollate;";

        $wpdb->query($sql);
    }

    public function getDescription(): string
    {
        return 'Create table for baseline components';
    }
}
