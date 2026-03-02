<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Configuration\Entity\SchemaDefinition;
use Pet\Domain\Configuration\Entity\SchemaStatus;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;

class SqlSchemaDefinitionRepository implements SchemaDefinitionRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_schema_definitions';
    }

    public function save(SchemaDefinition $schemaDefinition): void
    {
        $data = [
            'entity_type' => $schemaDefinition->entityType(),
            'version' => $schemaDefinition->version(),
            'schema_json' => json_encode($schemaDefinition->schema()),
            'status' => $schemaDefinition->status()->value,
            'published_at' => $schemaDefinition->publishedAt() ? $schemaDefinition->publishedAt()->format('Y-m-d H:i:s') : null,
            'published_by' => $schemaDefinition->publishedByEmployeeId(),
            'created_at' => $schemaDefinition->createdAt()->format('Y-m-d H:i:s'),
            'created_by_employee_id' => $schemaDefinition->createdByEmployeeId(),
        ];

        $format = ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d'];

        if ($schemaDefinition->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $schemaDefinition->id()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
        }
    }

    public function findById(int $id): ?SchemaDefinition
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findLatestByEntityType(string $entityType): ?SchemaDefinition
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE entity_type = %s ORDER BY version DESC LIMIT 1",
            $entityType
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findDraftByEntityType(string $entityType): ?SchemaDefinition
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE entity_type = %s AND status = 'draft' LIMIT 1",
            $entityType
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findActiveByEntityType(string $entityType): ?SchemaDefinition
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE entity_type = %s AND status = 'active' LIMIT 1",
            $entityType
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEntityType(string $entityType): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE entity_type = %s ORDER BY version DESC",
            $entityType
        );
        $rows = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByEntityTypeAndVersion(string $entityType, int $version): ?SchemaDefinition
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE entity_type = %s AND version = %d LIMIT 1",
            $entityType,
            $version
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function markActiveAsHistorical(string $entityType): void
    {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->tableName} SET status = %s WHERE entity_type = %s AND status = %s",
                SchemaStatus::HISTORICAL->value,
                $entityType,
                SchemaStatus::ACTIVE->value
            )
        );
    }

    private function hydrate(object $row): SchemaDefinition
    {
        $schemaData = json_decode($row->schema_json, true);

        // Fallback for double-encoded or escaped JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $schemaData = json_decode(stripslashes($row->schema_json), true);
        }

        return new SchemaDefinition(
            $row->entity_type,
            (int) $row->version,
            is_array($schemaData) ? $schemaData : [],
            (int) $row->id,
            SchemaStatus::tryFrom($row->status) ?? SchemaStatus::DRAFT,
            !empty($row->published_at) ? new \DateTimeImmutable($row->published_at) : null,
            !empty($row->published_by) ? (int) $row->published_by : null,
            new \DateTimeImmutable($row->created_at),
            $row->created_by_employee_id ? (int) $row->created_by_employee_id : null
        );
    }
}
