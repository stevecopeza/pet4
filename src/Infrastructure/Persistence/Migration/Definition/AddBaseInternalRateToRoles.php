<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddBaseInternalRateToRoles implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_roles';
        $colExists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'base_internal_rate'");
        if (empty($colExists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN base_internal_rate decimal(12,2) DEFAULT NULL AFTER success_criteria");
        }
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_roles';
        $wpdb->query("ALTER TABLE $table DROP COLUMN IF EXISTS base_internal_rate");
    }

    public function getDescription(): string
    {
        return 'Add base_internal_rate column to roles table';
    }
}
