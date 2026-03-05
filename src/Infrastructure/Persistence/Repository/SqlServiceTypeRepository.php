<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\ServiceType;
use Pet\Domain\Commercial\Repository\ServiceTypeRepository;

class SqlServiceTypeRepository implements ServiceTypeRepository
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'pet_service_types';
    }

    public function findById(int $id): ?ServiceType
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $rows = $this->wpdb->get_results("SELECT * FROM {$this->table} ORDER BY name ASC");
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findActive(): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE status = %s ORDER BY name ASC", 'active')
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(ServiceType $serviceType): void
    {
        $data = [
            'name' => $serviceType->name(),
            'description' => $serviceType->description(),
            'status' => $serviceType->status(),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $format = ['%s', '%s', '%s', '%s'];

        if ($serviceType->id()) {
            $this->wpdb->update($this->table, $data, ['id' => $serviceType->id()], $format, ['%d']);
        } else {
            $data['created_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $format[] = '%s';

            $sql = "INSERT INTO {$this->table} (name, description, status, updated_at, created_at)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        description = VALUES(description),
                        status = VALUES(status),
                        updated_at = VALUES(updated_at)";

            $this->wpdb->query($this->wpdb->prepare(
                $sql,
                $data['name'],
                $data['description'],
                $data['status'],
                $data['updated_at'],
                $data['created_at']
            ));

            $id = (int)$this->wpdb->insert_id;
            if ($id > 0) {
                $ref = new \ReflectionObject($serviceType);
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                $prop->setValue($serviceType, $id);
            }
        }
    }

    public function archive(int $id): void
    {
        $this->wpdb->update(
            $this->table,
            ['status' => 'archived', 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    private function hydrate(object $row): ServiceType
    {
        return new ServiceType(
            $row->name,
            $row->description,
            $row->status,
            (int)$row->id,
            new \DateTimeImmutable($row->created_at),
            new \DateTimeImmutable($row->updated_at)
        );
    }
}
