<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Identity\Entity\Customer;
use Pet\Domain\Identity\Repository\CustomerRepository;

class SqlCustomerRepository implements CustomerRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_customers';
    }

    public function save(Customer $customer): void
    {
        $data = [
            'name' => $customer->name(),
            'legal_name' => $customer->legalName(),
            'contact_email' => $customer->contactEmail(),
            'status' => $customer->status(),
            'malleable_schema_version' => $customer->malleableSchemaVersion(),
            'malleable_data' => !empty($customer->malleableData()) ? json_encode($customer->malleableData()) : null,
            'created_at' => $this->formatDate($customer->createdAt()),
            'archived_at' => $this->formatDate($customer->archivedAt()),
        ];

        $format = ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];

        if ($customer->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $customer->id()],
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

    public function findById(int $id): ?Customer
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

    private function hydrate(object $row): Customer
    {
        return new Customer(
            $row->name,
            isset($row->contact_email) ? $row->contact_email : '',
            (int) $row->id,
            isset($row->legal_name) ? $row->legal_name : null,
            isset($row->status) ? $row->status : 'active',
            isset($row->malleable_schema_version) ? (int) $row->malleable_schema_version : null,
            isset($row->malleable_data) ? (json_decode($row->malleable_data, true) ?: []) : [],
            new \DateTimeImmutable($row->created_at),
            isset($row->archived_at) && $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null
        );
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}
