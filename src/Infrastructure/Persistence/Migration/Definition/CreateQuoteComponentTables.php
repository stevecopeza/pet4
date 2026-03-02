<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateQuoteComponentTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // Update Quotes Table
        $quotesTable = $this->wpdb->prefix . 'pet_quotes';
        $col = $this->wpdb->get_results("SHOW COLUMNS FROM $quotesTable LIKE 'total_internal_cost'");
        if (empty($col)) {
            $this->wpdb->query("ALTER TABLE $quotesTable ADD COLUMN total_internal_cost decimal(14, 2) NOT NULL DEFAULT 0.00 AFTER total_value");
        }
        $this->wpdb->query("ALTER TABLE $quotesTable MODIFY COLUMN total_value decimal(14, 2) NOT NULL DEFAULT 0.00");

        // Quote Components
        $componentsTable = $this->wpdb->prefix . 'pet_quote_components';
        $componentsSql = "CREATE TABLE $componentsTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quote_id mediumint(9) NOT NULL,
            type varchar(50) NOT NULL,
            description text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) $charsetCollate;";

        // Quote Milestones (for Implementation Components)
        $milestonesTable = $this->wpdb->prefix . 'pet_quote_milestones';
        $milestonesSql = "CREATE TABLE $milestonesTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            component_id mediumint(9) NOT NULL,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY component_id (component_id)
        ) $charsetCollate;";

        // Quote Tasks (for Milestones)
        $tasksTable = $this->wpdb->prefix . 'pet_quote_tasks';
        $tasksSql = "CREATE TABLE $tasksTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            milestone_id mediumint(9) NOT NULL,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            duration_hours decimal(8, 2) NOT NULL DEFAULT 0.00,
            role_id mediumint(9) NOT NULL,
            base_internal_rate decimal(12, 2) NOT NULL DEFAULT 0.00,
            sell_rate decimal(12, 2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            KEY milestone_id (milestone_id)
        ) $charsetCollate;";

        // Quote Recurring Services (for Recurring Components)
        $recurringTable = $this->wpdb->prefix . 'pet_quote_recurring_services';
        $recurringSql = "CREATE TABLE $recurringTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            component_id mediumint(9) NOT NULL,
            service_name varchar(255) NOT NULL,
            sla_snapshot json DEFAULT NULL,
            cadence varchar(50) NOT NULL,
            term_months int NOT NULL DEFAULT 12,
            renewal_model varchar(50) NOT NULL,
            sell_price_per_period decimal(14, 2) NOT NULL DEFAULT 0.00,
            internal_cost_per_period decimal(14, 2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            KEY component_id (component_id)
        ) $charsetCollate;";

        // Quote Catalog Items (for Catalog Components)
        $catalogTable = $this->wpdb->prefix . 'pet_quote_catalog_items';
        $catalogSql = "CREATE TABLE $catalogTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            component_id mediumint(9) NOT NULL,
            description varchar(255) NOT NULL,
            quantity decimal(10, 2) NOT NULL DEFAULT 1.00,
            unit_sell_price decimal(14, 2) NOT NULL DEFAULT 0.00,
            unit_internal_cost decimal(14, 2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            KEY component_id (component_id)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($componentsSql);
        dbDelta($milestonesSql);
        dbDelta($tasksSql);
        dbDelta($recurringSql);
        dbDelta($catalogSql);
    }

    public function getDescription(): string
    {
        return 'Create quote component tables and update quotes table.';
    }
}
