<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

final class SqlExternalMappingRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function upsert(string $system, string $entityType, int $petEntityId, string $externalId, ?string $externalVersion = null): void
    {
        $table = $this->wpdb->prefix . 'pet_external_mappings';
        $charsetCollate = $this->wpdb->get_charset_collate();
        $createSql = "
            CREATE TABLE IF NOT EXISTS $table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `system` varchar(32) NOT NULL,
                entity_type varchar(64) NOT NULL,
                pet_entity_id bigint(20) NOT NULL,
                external_id varchar(128) NOT NULL,
                external_version varchar(64) DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_pet_entity (`system`, entity_type, pet_entity_id),
                UNIQUE KEY uniq_external (`system`, entity_type, external_id)
            ) $charsetCollate
        ";
        $this->wpdb->query($createSql);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $sql = "
            INSERT INTO $table (`system`, entity_type, pet_entity_id, external_id, external_version, created_at, updated_at)
            VALUES (%s, %s, %d, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE external_version = VALUES(external_version), updated_at = VALUES(updated_at)
        ";
        $prepared = $this->wpdb->prepare($sql, [$system, $entityType, $petEntityId, $externalId, $externalVersion, $now, $now]);
        $this->wpdb->query($prepared);
    }

    public function exists(string $system, string $entityType, int $petEntityId): bool
    {
        $table = $this->wpdb->prefix . 'pet_external_mappings';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return false;
        }
        $row = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM $table WHERE `system` = %s AND entity_type = %s AND pet_entity_id = %d LIMIT 1",
            [$system, $entityType, $petEntityId]
        ));
        return $row !== null;
    }
}
