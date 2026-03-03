<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddPublicHolidayFieldsToCalendars implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_calendars';

        $wpdb->query("ALTER TABLE $table ADD COLUMN exclude_public_holidays tinyint(1) NOT NULL DEFAULT 0 AFTER is_default");
        $wpdb->query("ALTER TABLE $table ADD COLUMN public_holiday_country varchar(2) DEFAULT NULL AFTER exclude_public_holidays");
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_calendars';
        $wpdb->query("ALTER TABLE $table DROP COLUMN exclude_public_holidays");
        $wpdb->query("ALTER TABLE $table DROP COLUMN public_holiday_country");
    }

    public function getDescription(): string
    {
        return 'Add exclude_public_holidays and public_holiday_country columns to calendars table';
    }
}
