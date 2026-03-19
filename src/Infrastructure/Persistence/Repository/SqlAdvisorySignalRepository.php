<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use DateTimeImmutable;

class SqlAdvisorySignalRepository implements AdvisorySignalRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(AdvisorySignal $signal): void
    {
        $table = $this->wpdb->prefix . 'pet_advisory_signals';
        
        $data = [
            'id' => $signal->getId(),
            'work_item_id' => $signal->getWorkItemId(),
            'signal_type' => $signal->getSignalType(),
            'severity' => $signal->getSeverity(),
            'status' => $signal->getStatus(),
            'resolved_at' => $signal->getResolvedAt()?->format('Y-m-d H:i:s'),
            'generation_run_id' => $signal->getGenerationRunId(),
            'title' => $signal->getTitle(),
            'summary' => $signal->getSummary(),
            'metadata_json' => $signal->getMetadata() ? json_encode($signal->getMetadata(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'source_entity_type' => $signal->getSourceEntityType(),
            'source_entity_id' => $signal->getSourceEntityId(),
            'customer_id' => $signal->getCustomerId(),
            'site_id' => $signal->getSiteId(),
            'message' => $signal->getMessage(),
            'created_at' => $signal->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        $this->wpdb->insert($table, $data);
    }

    public function findByWorkItemId(string $workItemId): array
    {
        $table = $this->wpdb->prefix . 'pet_advisory_signals';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE work_item_id = %s ORDER BY created_at DESC",
            $workItemId
        ));

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findByWorkItemIds(array $workItemIds): array
    {
        if (empty($workItemIds)) {
            return [];
        }

        $table = $this->wpdb->prefix . 'pet_advisory_signals';
        $placeholders = implode(',', array_fill(0, count($workItemIds), '%s'));
        
        $query = "SELECT * FROM $table WHERE work_item_id IN ($placeholders) ORDER BY created_at DESC";
        
        $rows = $this->wpdb->get_results($this->wpdb->prepare($query, ...$workItemIds));

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findActiveByWorkItemId(string $workItemId): array
    {
        $table = $this->wpdb->prefix . 'pet_advisory_signals';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE work_item_id = %s AND status = %s ORDER BY created_at DESC",
            $workItemId,
            'ACTIVE'
        ));
        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findRecent(int $limit): array
    {
        $table = $this->wpdb->prefix . 'pet_advisory_signals';
        
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
            $limit
        ));

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function clearForWorkItem(string $workItemId, ?string $generationRunId = null): void
    {
        $table = $this->wpdb->prefix . 'pet_advisory_signals';
        $data = [
            'status' => 'INACTIVE',
            'resolved_at' => current_time('mysql', true),
        ];
        if ($generationRunId !== null) {
            $data['generation_run_id'] = $generationRunId;
        }

        $this->wpdb->update(
            $table,
            $data,
            [
                'work_item_id' => $workItemId,
                'status' => 'ACTIVE',
            ]
        );
    }

    private function mapRowToEntity(object $row): AdvisorySignal
    {
        $metadata = null;
        if (isset($row->metadata_json) && $row->metadata_json) {
            $decoded = json_decode((string)$row->metadata_json, true);
            $metadata = is_array($decoded) ? $decoded : null;
        }
        return new AdvisorySignal(
            $row->id,
            $row->work_item_id,
            $row->signal_type,
            $row->severity,
            $row->message,
            new DateTimeImmutable($row->created_at),
            isset($row->status) ? (string)$row->status : 'ACTIVE',
            isset($row->resolved_at) && $row->resolved_at ? new DateTimeImmutable($row->resolved_at) : null,
            isset($row->generation_run_id) && $row->generation_run_id ? (string)$row->generation_run_id : null,
            isset($row->title) && $row->title ? (string)$row->title : null,
            isset($row->summary) && $row->summary ? (string)$row->summary : null,
            $metadata,
            isset($row->source_entity_type) && $row->source_entity_type ? (string)$row->source_entity_type : null,
            isset($row->source_entity_id) && $row->source_entity_id ? (string)$row->source_entity_id : null,
            isset($row->customer_id) && $row->customer_id !== null ? (int)$row->customer_id : null,
            isset($row->site_id) && $row->site_id !== null ? (int)$row->site_id : null
        );
    }
}
