<?php

declare(strict_types=1);

namespace Pet\Domain\Event\Repository;

class EventRecord
{
    public int $id;
    public string $eventUuid;
    public string $occurredAt;
    public string $recordedAt;
    public string $aggregateType;
    public int $aggregateId;
    public int $aggregateVersion;
    public string $eventType;
    public int $eventSchemaVersion;
    public ?string $actorType;
    public ?int $actorId;
    public ?string $correlationId;
    public ?string $causationId;
    public string $payloadJson;
    public ?string $metadataJson;
}

interface EventStreamRepository
{
    /**
     * Fetch latest events, optionally filtered.
     *
     * @param int $limit
     * @param string|null $aggregateType
     * @param int|null $aggregateId
     * @param string|null $eventType
     * @return EventRecord[]
     */
    public function findLatest(
        int $limit = 100,
        ?string $aggregateType = null,
        ?int $aggregateId = null,
        ?string $eventType = null
    ): array;

    /**
     * Append a domain event to the event stream (insert-only).
     *
     * @param string $aggregateType
     * @param int $aggregateId
     * @param int $aggregateVersion
     * @param string $eventType
     * @param string $payloadJson
     * @param string|null $metadataJson
     * @param string|null $actorType
     * @param int|null $actorId
     * @param string|null $correlationId
     * @param string|null $causationId
     * @return int Inserted event id
     */
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
    ): int;

    /**
     * Find a single event by id.
     */
    public function findById(int $id): ?EventRecord;

    /**
     * Get next aggregate version (last + 1).
     */
    public function nextVersion(string $aggregateType, int $aggregateId): int;
}
