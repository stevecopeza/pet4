<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateContractBaselineTables implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Contracts
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pet_contracts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) NOT NULL,
            customer_id bigint(20) NOT NULL,
            status varchar(50) NOT NULL,
            total_value decimal(10, 2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            start_date datetime NOT NULL,
            end_date datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Baselines
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pet_baselines (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) NOT NULL,
            total_value decimal(10, 2) NOT NULL DEFAULT 0.00,
            total_internal_cost decimal(10, 2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id)
        ) $charset_collate;";

        dbDelta($sql);

        // Baseline Components
        // Storing components as a JSON snapshot for the baseline to ensure perfect immutability and simplicity
        // for this phase. If we need deep querying later, we can expand.
        // Actually, let's stick to the separate table `pet_baseline_components` defined in thought, 
        // but keep `component_data` as JSON to handle polymorphism easily.
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pet_baseline_components (
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
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    public function down(): void
    {
        // No down migration
    }

    public function getDescription(): string
    {
        return 'Create tables for Contracts, Baselines, and Baseline Components';
    }
}
