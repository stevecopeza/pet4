<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\Capability;
use Pet\Domain\Work\Repository\CapabilityRepository;

class SqlCapabilityRepository implements CapabilityRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_capabilities';
    }

    public function save(Capability $capability): void
    {
        $data = [
            'name' => $capability->name(),
            'description' => $capability->description(),
            'parent_id' => $capability->parentId(),
            'status' => $capability->status(),
            'created_at' => $capability->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%s', '%s', '%d', '%s', '%s'];

        if ($capability->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $capability->id()],
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

    public function findById(int $id): ?Capability
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->tableName} ORDER BY name ASC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    private function hydrate(object $row): Capability
    {
        return new Capability(
            $row->name,
            $row->description,
            (int) $row->id,
            $row->parent_id ? (int) $row->parent_id : null,
            $row->status,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
