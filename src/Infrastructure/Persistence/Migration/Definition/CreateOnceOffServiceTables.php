<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateOnceOffServiceTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        $phasesTable = $this->wpdb->prefix . 'pet_quote_onceoff_phases';
        $phasesSql = "CREATE TABLE $phasesTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            component_id mediumint(9) NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY component_id (component_id)
        ) $charsetCollate;";

        $unitsTable = $this->wpdb->prefix . 'pet_quote_onceoff_units';
        $unitsSql = "CREATE TABLE $unitsTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            component_id mediumint(9) NOT NULL,
            phase_id mediumint(9) DEFAULT NULL,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            quantity decimal(10, 2) NOT NULL DEFAULT 1.00,
            unit_sell_price decimal(14, 2) NOT NULL DEFAULT 0.00,
            unit_internal_cost decimal(14, 2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            KEY component_id (component_id),
            KEY phase_id (phase_id)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($phasesSql);
        dbDelta($unitsSql);
    }

    public function getDescription(): string
    {
        return 'Create tables for Once-off Service quote phases and units.';
    }
}

