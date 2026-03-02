<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use wpdb;
use DateTimeImmutable;

class SqlAdvisorySignalRepository implements AdvisorySignalRepository
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
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
            'message' => $signal->getMessage(),
            'created_at' => $signal->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        $this->wpdb->replace($table, $data);
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
        // For now, return all signals as we don't have a 'resolved' status yet.
        // Assuming signals are just logs/events for now.
        return $this->findByWorkItemId($workItemId);
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

    public function clearForWorkItem(string $workItemId): void
    {
        $table = $this->wpdb->prefix . 'pet_advisory_signals';
        $this->wpdb->delete($table, ['work_item_id' => $workItemId]);
    }

    private function mapRowToEntity(object $row): AdvisorySignal
    {
        return new AdvisorySignal(
            $row->id,
            $row->work_item_id,
            $row->signal_type,
            $row->severity,
            $row->message,
            new DateTimeImmutable($row->created_at)
        );
    }
}
