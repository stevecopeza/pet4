<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddIs24x7ToCalendars implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_calendars';

        $wpdb->query("ALTER TABLE $table ADD COLUMN is_24x7 tinyint(1) NOT NULL DEFAULT 0 AFTER is_default");
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_calendars';
        $wpdb->query("ALTER TABLE $table DROP COLUMN is_24x7");
    }

    public function getDescription(): string
    {
        return 'Add is_24x7 column to calendars table';
    }
}
