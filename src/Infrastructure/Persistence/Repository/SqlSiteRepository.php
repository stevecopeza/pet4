<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Identity\Entity\Site;
use Pet\Domain\Identity\Repository\SiteRepository;

class SqlSiteRepository implements SiteRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_sites';
    }

    public function save(Site $site): void
    {
        $data = [
            'customer_id' => $site->customerId(),
            'name' => $site->name(),
            'address_lines' => $site->addressLines(),
            'city' => $site->city(),
            'state' => $site->state(),
            'postal_code' => $site->postalCode(),
            'country' => $site->country(),
            'status' => $site->status(),
            'malleable_schema_version' => $site->malleableSchemaVersion(),
            'malleable_data' => !empty($site->malleableData()) ? json_encode($site->malleableData()) : null,
            'created_at' => $this->formatDate($site->createdAt()),
            'archived_at' => $this->formatDate($site->archivedAt()),
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];

        if ($site->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $site->id()],
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

    public function findById(int $id): ?Site
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCustomerId(int $customerId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE customer_id = %d AND archived_at IS NULL ORDER BY name ASC",
            $customerId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE archived_at IS NULL ORDER BY name ASC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function delete(int $id): void
    {
        $this->wpdb->update(
            $this->tableName,
            ['archived_at' => current_time('mysql')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    private function hydrate(object $row): Site
    {
        return new Site(
            (int) $row->customer_id,
            $row->name,
            $row->address_lines,
            $row->city,
            $row->state,
            $row->postal_code,
            $row->country,
            isset($row->status) ? $row->status : 'active',
            (int) $row->id,
            $row->malleable_schema_version ? (int) $row->malleable_schema_version : null,
            $row->malleable_data ? json_decode($row->malleable_data, true) : [],
            new \DateTimeImmutable($row->created_at),
            $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null
        );
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}
