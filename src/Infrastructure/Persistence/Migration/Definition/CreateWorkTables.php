<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateWorkTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // 1. Proficiency Levels
        $proficiencyTable = $this->wpdb->prefix . 'pet_proficiency_levels';
        $sqlProficiency = "CREATE TABLE IF NOT EXISTS $proficiencyTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level_number int(11) NOT NULL,
            name varchar(100) NOT NULL,
            definition text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY level_number (level_number)
        ) $charsetCollate;";

        // 2. Capabilities
        $capabilitiesTable = $this->wpdb->prefix . 'pet_capabilities';
        $sqlCapabilities = "CREATE TABLE IF NOT EXISTS $capabilitiesTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NOT NULL,
            parent_id bigint(20) UNSIGNED DEFAULT NULL,
            status varchar(50) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY parent_id (parent_id)
        ) $charsetCollate;";

        // 3. Skills
        $skillsTable = $this->wpdb->prefix . 'pet_skills';
        $sqlSkills = "CREATE TABLE IF NOT EXISTS $skillsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            capability_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text NOT NULL,
            status varchar(50) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY capability_id (capability_id)
        ) $charsetCollate;";

        // 4. Roles (Versioned)
        $rolesTable = $this->wpdb->prefix . 'pet_roles';
        $sqlRoles = "CREATE TABLE IF NOT EXISTS $rolesTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            version int(11) DEFAULT 1 NOT NULL,
            status varchar(50) DEFAULT 'draft' NOT NULL,
            level varchar(50) NOT NULL,
            description text NOT NULL,
            success_criteria text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            published_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charsetCollate;";

        // 5. Role Skills (Join Table)
        $roleSkillsTable = $this->wpdb->prefix . 'pet_role_skills';
        $sqlRoleSkills = "CREATE TABLE IF NOT EXISTS $roleSkillsTable (
            role_id bigint(20) UNSIGNED NOT NULL,
            skill_id bigint(20) UNSIGNED NOT NULL,
            min_proficiency_level int(11) NOT NULL,
            importance_weight int(11) DEFAULT 1 NOT NULL,
            PRIMARY KEY (role_id, skill_id)
        ) $charsetCollate;";

        // 6. Certifications
        $certificationsTable = $this->wpdb->prefix . 'pet_certifications';
        $sqlCertifications = "CREATE TABLE IF NOT EXISTS $certificationsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            issuing_body varchar(255) NOT NULL,
            expiry_months int(11) DEFAULT 0 NOT NULL,
            status varchar(50) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charsetCollate;";

        // 7. Person Role Assignments
        $assignmentsTable = $this->wpdb->prefix . 'pet_person_role_assignments';
        $sqlAssignments = "CREATE TABLE IF NOT EXISTS $assignmentsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            role_id bigint(20) UNSIGNED NOT NULL,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            allocation_pct int(11) DEFAULT 100 NOT NULL,
            status varchar(50) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY role_id (role_id)
        ) $charsetCollate;";

        // 8. Person Skills (Ratings)
        $personSkillsTable = $this->wpdb->prefix . 'pet_person_skills';
        $sqlPersonSkills = "CREATE TABLE IF NOT EXISTS $personSkillsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            skill_id bigint(20) UNSIGNED NOT NULL,
            review_cycle_id bigint(20) UNSIGNED DEFAULT NULL,
            self_rating int(11) DEFAULT 0 NOT NULL,
            manager_rating int(11) DEFAULT 0 NOT NULL,
            effective_date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY skill_id (skill_id)
        ) $charsetCollate;";

        // 9. Person Certifications
        $personCertificationsTable = $this->wpdb->prefix . 'pet_person_certifications';
        $sqlPersonCertifications = "CREATE TABLE IF NOT EXISTS $personCertificationsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            certification_id bigint(20) UNSIGNED NOT NULL,
            obtained_date date NOT NULL,
            expiry_date date DEFAULT NULL,
            evidence_url varchar(255) DEFAULT NULL,
            status varchar(50) DEFAULT 'valid' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY certification_id (certification_id)
        ) $charsetCollate;";

        // 10. KPI Definitions
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

        // 11. Role KPIs
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

        // 12. Person KPI Instances
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
        dbDelta($sqlProficiency);
        dbDelta($sqlCapabilities);
        dbDelta($sqlSkills);
        dbDelta($sqlRoles);
        dbDelta($sqlRoleSkills);
        dbDelta($sqlCertifications);
        dbDelta($sqlAssignments);
        dbDelta($sqlPersonSkills);
        dbDelta($sqlPersonCertifications);
        dbDelta($sqlKpiDefinitions);
        dbDelta($sqlRoleKpis);
        dbDelta($sqlPersonKpis);
    }

    public function getDescription(): string
    {
        return 'Create Work Domain tables: Roles, Skills, Capabilities, Certifications, Assignments, and KPIs.';
    }
}
