<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration;

class MigrationRunner
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function run(array $migrations): void
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();

        foreach ($migrations as $className) {
            if (in_array($className, $applied, true)) {
                continue;
            }

            /** @var Migration $migration */
            $migration = new $className($this->wpdb);
            $migration->up();

            $this->recordMigration($className);
        }
    }

    private function ensureMigrationsTable(): void
    {
        $tableName = $this->wpdb->prefix . 'pet_migrations';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            migration_class varchar(255) NOT NULL,
            executed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY migration_class (migration_class)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function getAppliedMigrations(): array
    {
        $tableName = $this->wpdb->prefix . 'pet_migrations';
        
        // Check if table exists first to avoid errors on fresh install
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName) {
            return [];
        }

        return $this->wpdb->get_col("SELECT migration_class FROM $tableName");
    }

    private function recordMigration(string $className): void
    {
        $tableName = $this->wpdb->prefix . 'pet_migrations';
        $this->wpdb->insert(
            $tableName,
            ['migration_class' => $className]
        );
    }
}
