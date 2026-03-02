<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AlterDemoSeedRegistryTableAddColumns implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_demo_seed_registry';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        $columns = $wpdb->get_col("DESCRIBE $table", 0);

        $addColumn = function (string $name, string $definition) use ($wpdb, $table, $columns): void {
            if (!in_array($name, $columns, true)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN $name $definition");
            }
        };

        $addColumn('purge_status', "varchar(32) NOT NULL DEFAULT 'ACTIVE'");
        $addColumn('purged_at', "datetime NULL");
        $addColumn('skip_reason', "varchar(128) NULL");
        $addColumn('user_touched', "tinyint(1) NOT NULL DEFAULT 0");
        $addColumn('last_seen_at', "datetime NULL");
    }

    public function getDescription(): string
    {
        return 'Extend demo seed registry with purge_status, purged_at, skip_reason, user_touched, last_seen_at';
    }

    public function down(): void
    {
        // Forward-only per invariants; no down migration
    }
}
