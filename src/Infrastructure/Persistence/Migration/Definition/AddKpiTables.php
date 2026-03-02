<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddKpiTables implements Migration
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        $kpiDefinitionsTable = $this->wpdb->prefix . 'pet_kpi_definitions';
        $sqlKpiDefinitions = "CREATE TABLE IF NOT EXISTS $kpiDefinitionsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NOT NULL,
            default_frequency varchar(50) DEFAULT 'monthly' NOT NULL,
            unit varchar(50) DEFAULT '%' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charsetCollate;";

        $roleKpisTable = $this->wpdb->prefix . 'pet_role_kpis';
        $sqlRoleKpis = "CREATE TABLE IF NOT EXISTS $roleKpisTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            role_id bigint(20) UNSIGNED NOT NULL,
            kpi_definition_id bigint(20) UNSIGNED NOT NULL,
            weight_percentage int(11) NOT NULL,
            target_value decimal(10,2) NOT NULL,
            measurement_frequency varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY role_id (role_id),
            KEY kpi_definition_id (kpi_definition_id)
        ) $charsetCollate;";

        $personKpisTable = $this->wpdb->prefix . 'pet_person_kpis';
        $sqlPersonKpis = "CREATE TABLE IF NOT EXISTS $personKpisTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            kpi_definition_id bigint(20) UNSIGNED NOT NULL,
            role_id bigint(20) UNSIGNED NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            target_value decimal(10,2) NOT NULL,
            actual_value decimal(10,2) DEFAULT NULL,
            score decimal(10,2) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY role_id (role_id),
            KEY period_start (period_start)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlKpiDefinitions);
        dbDelta($sqlRoleKpis);
        dbDelta($sqlPersonKpis);
    }

    public function getDescription(): string
    {
        return 'Add KPI tables (definitions, role_kpis, person_kpis) forward-only.';
    }
}
