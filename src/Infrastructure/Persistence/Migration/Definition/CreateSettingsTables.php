<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateSettingsTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'pet_settings';

        $sql = "CREATE TABLE $table (
            setting_key varchar(100) NOT NULL,
            setting_value longtext NOT NULL,
            setting_type varchar(20) NOT NULL DEFAULT 'string',
            description varchar(255) NOT NULL DEFAULT '',
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (setting_key)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create settings table.';
    }
}
