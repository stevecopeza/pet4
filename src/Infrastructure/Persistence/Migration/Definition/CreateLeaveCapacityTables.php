<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateLeaveCapacityTables implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $types = $wpdb->prefix . 'pet_leave_types';
        if ($wpdb->get_var("SHOW TABLES LIKE '$types'") !== $types) {
            $sql = "CREATE TABLE $types (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(64) NOT NULL,
                paid_flag tinyint(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY name (name)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        $requests = $wpdb->prefix . 'pet_leave_requests';
        if ($wpdb->get_var("SHOW TABLES LIKE '$requests'") !== $requests) {
            $sql = "CREATE TABLE $requests (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                employee_id bigint(20) NOT NULL,
                leave_type_id bigint(20) NOT NULL,
                start_date date NOT NULL,
                end_date date NOT NULL,
                status varchar(16) NOT NULL,
                submitted_at datetime DEFAULT NULL,
                decided_by_employee_id bigint(20) DEFAULT NULL,
                decided_at datetime DEFAULT NULL,
                decision_reason text DEFAULT NULL,
                notes text DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uuid (uuid),
                KEY employee_status (employee_id, status),
                KEY period (start_date, end_date)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        $overrides = $wpdb->prefix . 'pet_capacity_overrides';
        if ($wpdb->get_var("SHOW TABLES LIKE '$overrides'") !== $overrides) {
            $sql = "CREATE TABLE $overrides (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                employee_id bigint(20) NOT NULL,
                effective_date date NOT NULL,
                capacity_pct int(11) NOT NULL,
                reason varchar(255) DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_override (employee_id, effective_date),
                KEY employee_date (employee_id, effective_date)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }

    public function getDescription(): string
    {
        return 'Create leave types/requests and capacity overrides tables';
    }
}

