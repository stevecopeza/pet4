<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateCalendarTables implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $calendarsTable = $wpdb->prefix . 'pet_calendars';
        $windowsTable = $wpdb->prefix . 'pet_calendar_working_windows';
        $holidaysTable = $wpdb->prefix . 'pet_calendar_holidays';

        // 1. Calendars Table
        $sqlCalendars = "CREATE TABLE $calendarsTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid char(36) NOT NULL,
            name varchar(255) NOT NULL,
            timezone varchar(100) NOT NULL DEFAULT 'UTC',
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid)
        ) $charsetCollate;";

        // 2. Working Windows Table
        $sqlWindows = "CREATE TABLE $windowsTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            calendar_id bigint(20) unsigned NOT NULL,
            day_of_week tinyint(1) NOT NULL, -- 0=Sunday, 1=Monday, etc.
            start_time time NOT NULL,
            end_time time NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'standard', -- standard, overtime
            rate_multiplier decimal(5,2) NOT NULL DEFAULT 1.00,
            PRIMARY KEY  (id),
            KEY calendar_id (calendar_id),
            FOREIGN KEY (calendar_id) REFERENCES $calendarsTable(id) ON DELETE CASCADE
        ) $charsetCollate;";

        // 3. Holidays Table
        $sqlHolidays = "CREATE TABLE $holidaysTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            calendar_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            holiday_date date NOT NULL,
            is_recurring tinyint(1) NOT NULL DEFAULT 0, -- e.g. Christmas
            PRIMARY KEY  (id),
            KEY calendar_id (calendar_id),
            FOREIGN KEY (calendar_id) REFERENCES $calendarsTable(id) ON DELETE CASCADE
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlCalendars);
        dbDelta($sqlWindows);
        dbDelta($sqlHolidays);
    }

    public function down(): void
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . 'pet_calendar_holidays');
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . 'pet_calendar_working_windows');
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . 'pet_calendars');
    }

    public function getDescription(): string
    {
        return 'Create calendar, working windows, and holiday tables';
    }
}
