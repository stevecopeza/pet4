<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Event\Repository\EventRecord;
use Pet\Domain\Event\Repository\EventStreamRepository;

class SqlEventStreamRepository implements EventStreamRepository
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function append(
        string $aggregateType,
        int $aggregateId,
        int $aggregateVersion,
        string $eventType,
        string $payloadJson,
        ?string $metadataJson = null,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $correlationId = null,
        ?string $causationId = null
    ): int {
        $table = $this->wpdb->prefix . 'pet_domain_event_stream';
        $expected = $this->nextVersion($aggregateType, $aggregateId);
        if ($aggregateVersion !== $expected) {
            throw new \RuntimeException('Aggregate version must be monotonic. Expected ' . $expected . ' got ' . $aggregateVersion);
        }
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $uuid = wp_generate_uuid4();
        $this->wpdb->insert($table, [
            'event_uuid' => $uuid,
            'occurred_at' => $now,
            'recorded_at' => $now,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'aggregate_version' => $aggregateVersion,
            'event_type' => $eventType,
            'event_schema_version' => 1,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'payload_json' => $payloadJson,
            'metadata_json' => $metadataJson,
        ]);
        return (int)$this->wpdb->insert_id;
    }

    public function nextVersion(string $aggregateType, int $aggregateId): int
    {
        $table = $this->wpdb->prefix . 'pet_domain_event_stream';
        $sql = "SELECT MAX(aggregate_version) AS maxv FROM $table WHERE aggregate_type = %s AND aggregate_id = %d";
        $prepared = $this->wpdb->prepare($sql, [$aggregateType, $aggregateId]);
        $row = $this->wpdb->get_row($prepared, \ARRAY_A);
        $max = $row && $row['maxv'] !== null ? (int)$row['maxv'] : 0;
        return $max + 1;
    }

    public function findById(int $id): ?EventRecord
    {
        $table = $this->wpdb->prefix . 'pet_domain_event_stream';
        $sql = "SELECT id, event_uuid, occurred_at, recorded_at, aggregate_type, aggregate_id, aggregate_version, event_type, event_schema_version, actor_type, actor_id, correlation_id, causation_id, payload_json, metadata_json
                FROM $table WHERE id = %d";
        $prepared = $this->wpdb->prepare($sql, [$id]);
        $row = $this->wpdb->get_row($prepared, ARRAY_A);
        if (!$row) return null;
        $rec = new EventRecord();
        $rec->id = (int)$row['id'];
        $rec->eventUuid = $row['event_uuid'];
        $rec->occurredAt = $row['occurred_at'];
        $rec->recordedAt = $row['recorded_at'];
        $rec->aggregateType = $row['aggregate_type'];
        $rec->aggregateId = (int)$row['aggregate_id'];
        $rec->aggregateVersion = (int)$row['aggregate_version'];
        $rec->eventType = $row['event_type'];
        $rec->eventSchemaVersion = (int)$row['event_schema_version'];
        $rec->actorType = $row['actor_type'] ?: null;
        $rec->actorId = $row['actor_id'] !== null ? (int)$row['actor_id'] : null;
        $rec->correlationId = $row['correlation_id'] ?: null;
        $rec->causationId = $row['causation_id'] ?: null;
        $rec->payloadJson = $row['payload_json'];
        $rec->metadataJson = $row['metadata_json'] ?: null;
        return $rec;
    }

    public function findLatest(
        int $limit = 100,
        ?string $aggregateType = null,
        ?int $aggregateId = null,
        ?string $eventType = null
    ): array {
        $table = $this->wpdb->prefix . 'pet_domain_event_stream';
        $where = [];
        $params = [];

        if ($aggregateType !== null) {
            $where[] = 'aggregate_type = %s';
            $params[] = $aggregateType;
        }
        if ($aggregateId !== null) {
            $where[] = 'aggregate_id = %d';
            $params[] = $aggregateId;
        }
        if ($eventType !== null) {
            $where[] = 'event_type = %s';
            $params[] = $eventType;
        }

        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT id, event_uuid, occurred_at, recorded_at, aggregate_type, aggregate_id, aggregate_version, event_type, event_schema_version, actor_type, actor_id, correlation_id, causation_id, payload_json, metadata_json
                FROM $table
                $whereSql
                ORDER BY id DESC
                LIMIT %d";
        $params[] = $limit;

        $prepared = $this->wpdb->prepare($sql, $params);
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);

        $out = [];
        foreach ($rows as $row) {
            $rec = new EventRecord();
            $rec->id = (int)$row['id'];
            $rec->eventUuid = $row['event_uuid'];
            $rec->occurredAt = $row['occurred_at'];
            $rec->recordedAt = $row['recorded_at'];
            $rec->aggregateType = $row['aggregate_type'];
            $rec->aggregateId = (int)$row['aggregate_id'];
            $rec->aggregateVersion = (int)$row['aggregate_version'];
            $rec->eventType = $row['event_type'];
            $rec->eventSchemaVersion = (int)$row['event_schema_version'];
            $rec->actorType = $row['actor_type'] ?: null;
            $rec->actorId = $row['actor_id'] !== null ? (int)$row['actor_id'] : null;
            $rec->correlationId = $row['correlation_id'] ?: null;
            $rec->causationId = $row['causation_id'] ?: null;
            $rec->payloadJson = $row['payload_json'];
            $rec->metadataJson = $row['metadata_json'] ?: null;
            $out[] = $rec;
        }
        return $out;
    }
}
