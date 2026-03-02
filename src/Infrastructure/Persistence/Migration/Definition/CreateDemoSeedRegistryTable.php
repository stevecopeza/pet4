<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateDemoSeedRegistryTable implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table = $wpdb->prefix . 'pet_demo_seed_registry';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $sql = "CREATE TABLE $table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                seed_run_id char(36) NOT NULL,
                table_name varchar(128) NOT NULL,
                row_id varchar(64) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY seed_run (seed_run_id),
                KEY table_row (table_name, row_id)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }

    public function getDescription(): string
    {
        return 'Add registry for demo seed rows to enable deterministic purge by seed_run_id';
    }

    public function down(): void
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pet_demo_seed_registry");
    }
}
