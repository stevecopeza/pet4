<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Entity\EscalationTransition;
use Pet\Domain\Escalation\Repository\EscalationRepository;
use Pet\Infrastructure\Persistence\Exception\DuplicateKeyException;

class SqlEscalationRepository implements EscalationRepository
{
    /** @var \wpdb|object */
    private $wpdb;

    /**
     * @param \wpdb|object $wpdb  WordPress database instance (or compatible stub for testing)
     */
    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(Escalation $escalation): void
    {
        $table = $this->wpdb->prefix . 'pet_escalations';
        $data = [
            'escalation_id' => $escalation->escalationId(),
            'source_entity_type' => $escalation->sourceEntityType(),
            'source_entity_id' => $escalation->sourceEntityId(),
            'severity' => $escalation->severity(),
            'status' => $escalation->status(),
            'reason' => $escalation->reason(),
            'metadata_json' => $escalation->metadataJson(),
            'open_dedupe_key' => $escalation->openDedupeKey(),
            'created_by' => $escalation->createdBy(),
            'acknowledged_by' => $escalation->acknowledgedBy(),
            'resolved_by' => $escalation->resolvedBy(),
            'acknowledged_at' => $escalation->acknowledgedAt()?->format('Y-m-d H:i:s'),
            'resolved_at' => $escalation->resolvedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($escalation->id()) {
            $this->wpdb->update($table, $data, ['id' => $escalation->id()]);
        } else {
            $data['created_at'] = $escalation->createdAt()->format('Y-m-d H:i:s');
            $result = $this->wpdb->insert($table, $data);

            if ($result === false) {
                $error = $this->wpdb->last_error;
                if (stripos($error, 'Duplicate entry') !== false) {
                    throw new DuplicateKeyException(
                        "Duplicate key on escalation insert: {$error}"
                    );
                }
                throw new \RuntimeException(
                    "Failed to insert escalation: {$error}"
                );
            }

            $insertedId = $this->wpdb->insert_id;

            if ($insertedId) {
                $ref = new \ReflectionObject($escalation);
                if ($ref->hasProperty('id')) {
                    $prop = $ref->getProperty('id');
                    $prop->setAccessible(true);
                    $prop->setValue($escalation, (int)$insertedId);
                }
            }
        }
    }

    public function findById(int $id): ?Escalation
    {
        $table = $this->wpdb->prefix . 'pet_escalations';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findByEscalationId(string $escalationId): ?Escalation
    {
        $table = $this->wpdb->prefix . 'pet_escalations';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE escalation_id = %s",
            $escalationId
        ));

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findBySourceEntity(string $sourceEntityType, int $sourceEntityId): array
    {
        $table = $this->wpdb->prefix . 'pet_escalations';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE source_entity_type = %s AND source_entity_id = %d ORDER BY created_at DESC",
            $sourceEntityType,
            $sourceEntityId
        ));

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findOpen(): array
    {
        $table = $this->wpdb->prefix . 'pet_escalations';
        $rows = $this->wpdb->get_results(
            "SELECT * FROM $table WHERE status IN ('OPEN', 'ACKED') ORDER BY created_at DESC"
        );

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findOpenByDedupeKey(string $dedupeKey): ?Escalation
    {
        $table = $this->wpdb->prefix . 'pet_escalations';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE open_dedupe_key = %s AND status IN ('OPEN', 'ACKED') LIMIT 1",
            $dedupeKey
        ));

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $table = $this->wpdb->prefix . 'pet_escalations';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function count(): int
    {
        $table = $this->wpdb->prefix . 'pet_escalations';
        return (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    public function saveTransition(EscalationTransition $transition): void
    {
        $table = $this->wpdb->prefix . 'pet_escalation_transitions';
        $this->wpdb->insert($table, [
            'escalation_id' => $transition->escalationId(),
            'from_status' => $transition->fromStatus(),
            'to_status' => $transition->toStatus(),
            'transitioned_by' => $transition->transitionedBy(),
            'reason' => $transition->reason(),
            'transitioned_at' => $transition->transitionedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findTransitionsByEscalationId(int $escalationId): array
    {
        $table = $this->wpdb->prefix . 'pet_escalation_transitions';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE escalation_id = %d ORDER BY transitioned_at ASC",
            $escalationId
        ));
    }

    private function mapRowToEntity(object $row): Escalation
    {
        return new Escalation(
            (string)$row->escalation_id,
            (string)$row->source_entity_type,
            (int)$row->source_entity_id,
            (string)$row->severity,
            (string)$row->reason,
            $row->created_by !== null ? (int)$row->created_by : null,
            (string)($row->metadata_json ?? '{}'),
            (int)$row->id,
            (string)$row->status,
            new \DateTimeImmutable($row->created_at),
            $row->acknowledged_at ? new \DateTimeImmutable($row->acknowledged_at) : null,
            $row->resolved_at ? new \DateTimeImmutable($row->resolved_at) : null,
            $row->acknowledged_by !== null ? (int)$row->acknowledged_by : null,
            $row->resolved_by !== null ? (int)$row->resolved_by : null,
            isset($row->open_dedupe_key) ? ($row->open_dedupe_key !== null ? (string)$row->open_dedupe_key : null) : null
        );
    }
}
