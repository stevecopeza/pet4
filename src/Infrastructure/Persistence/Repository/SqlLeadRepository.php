<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Lead;
use Pet\Domain\Commercial\Repository\LeadRepository;

class SqlLeadRepository implements LeadRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(Lead $lead): void
    {
        $table = $this->wpdb->prefix . 'pet_leads';
        
        $data = [
            'customer_id' => $lead->customerId(),
            'subject' => $lead->subject(),
            'description' => $lead->description(),
            'status' => $lead->status(),
            'source' => $lead->source(),
            'estimated_value' => $lead->estimatedValue(),
            'malleable_schema_version' => $lead->malleableSchemaVersion(),
            'malleable_data' => !empty($lead->malleableData()) ? json_encode($lead->malleableData()) : null,
            'created_at' => $lead->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $lead->updatedAt() ? $lead->updatedAt()->format('Y-m-d H:i:s') : null,
            'converted_at' => $lead->convertedAt() ? $lead->convertedAt()->format('Y-m-d H:i:s') : null,
        ];

        $formats = ['%d', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s'];

        if ($lead->id()) {
            $this->wpdb->update($table, $data, ['id' => $lead->id()], $formats, ['%d']);
        } else {
            $this->wpdb->insert($table, $data, $formats);
        }
    }

    public function findById(int $id): ?Lead
    {
        $table = $this->wpdb->prefix . 'pet_leads';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_leads';
        $rows = $this->wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByCustomerId(int $customerId): array
    {
        $table = $this->wpdb->prefix . 'pet_leads';
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $table WHERE customer_id = %d ORDER BY created_at DESC", $customerId));

        return array_map([$this, 'hydrate'], $rows);
    }

    public function delete(int $id): void
    {
        $table = $this->wpdb->prefix . 'pet_leads';
        $this->wpdb->delete($table, ['id' => $id], ['%d']);
    }

    private function hydrate($row): Lead
    {
        return new Lead(
            (int) $row->customer_id,
            $row->subject,
            $row->description,
            $row->status,
            $row->source,
            isset($row->estimated_value) ? (float) $row->estimated_value : null,
            (int) $row->id,
            isset($row->malleable_schema_version) ? (int) $row->malleable_schema_version : null,
            isset($row->malleable_data) ? (json_decode($row->malleable_data, true) ?: []) : [],
            new \DateTimeImmutable($row->created_at),
            isset($row->updated_at) && $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null,
            isset($row->converted_at) && $row->converted_at ? new \DateTimeImmutable($row->converted_at) : null
        );
    }
}
