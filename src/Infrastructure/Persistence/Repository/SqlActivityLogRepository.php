<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Activity\Entity\ActivityLog;
use Pet\Domain\Activity\Repository\ActivityLogRepository;

class SqlActivityLogRepository implements ActivityLogRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(ActivityLog $log): void
    {
        $table = $this->wpdb->prefix . 'pet_activity_logs';
        
        $data = [
            'type' => $log->type(),
            'description' => $log->description(),
            'user_id' => $log->userId(),
            'related_entity_type' => $log->relatedEntityType(),
            'related_entity_id' => $log->relatedEntityId(),
            'created_at' => $log->createdAt()->format('Y-m-d H:i:s'),
        ];

        $formats = ['%s', '%s', '%d', '%s', '%d', '%s'];

        $this->wpdb->insert($table, $data, $formats);
    }

    public function findAll(int $limit = 50): array
    {
        $table = $this->wpdb->prefix . 'pet_activity_logs';
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d", $limit));

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByRelatedEntity(string $entityType, int $entityId): array
    {
        $table = $this->wpdb->prefix . 'pet_activity_logs';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE related_entity_type = %s AND related_entity_id = %d ORDER BY created_at DESC",
            $entityType,
            $entityId
        ));

        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate($row): ActivityLog
    {
        return new ActivityLog(
            $row->type,
            $row->description,
            $row->user_id ? (int) $row->user_id : null,
            $row->related_entity_type,
            $row->related_entity_id ? (int) $row->related_entity_id : null,
            (int) $row->id,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
