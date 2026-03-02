<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateAdminAuditLog implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_admin_audit_log';
        $charsetCollate = $this->wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(64) NOT NULL,
            mode varchar(32) NOT NULL,
            tables_dropped longtext NULL,
            backup_path varchar(255) NULL,
            seed_run_id char(36) NULL,
            evidence_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charsetCollate;";
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create admin audit log table';
    }
}
