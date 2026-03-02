<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

final class SqlOutboxRepository
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function enqueue(int $eventId, string $destination, ?\DateTimeImmutable $nextAttemptAt = null): int
    {
        $table = $this->wpdb->prefix . 'pet_outbox';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->wpdb->insert($table, [
            'event_id' => $eventId,
            'destination' => $destination,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_attempt_at' => ($nextAttemptAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int)$this->wpdb->insert_id;
    }

    public function findDue(string $destination, int $limit = 25): array
    {
        $table = $this->wpdb->prefix . 'pet_outbox';
        $sql = "
            SELECT id, event_id, destination, status, attempt_count, next_attempt_at, last_error, created_at, updated_at
            FROM $table
            WHERE destination = %s
              AND status = 'pending'
              AND (next_attempt_at IS NULL OR next_attempt_at <= %s)
            ORDER BY id ASC
            LIMIT %d
            FOR UPDATE
        ";
        $prepared = $this->wpdb->prepare($sql, [$destination, (new \DateTimeImmutable())->format('Y-m-d H:i:s'), $limit]);
        return $this->wpdb->get_results($prepared, ARRAY_A);
    }

    public function markSent(int $id): void
    {
        $table = $this->wpdb->prefix . 'pet_outbox';
        $this->wpdb->update($table, [
            'status' => 'sent',
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function markFailed(int $id, int $attemptCount, \DateTimeImmutable $nextAttemptAt, string $error): void
    {
        $table = $this->wpdb->prefix . 'pet_outbox';
        $this->wpdb->update($table, [
            'status' => 'pending',
            'attempt_count' => $attemptCount,
            'next_attempt_at' => $nextAttemptAt->format('Y-m-d H:i:s'),
            'last_error' => $error,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function markDead(int $id, string $error): void
    {
        $table = $this->wpdb->prefix . 'pet_outbox';
        $this->wpdb->update($table, [
            'status' => 'dead',
            'last_error' => $error,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function findByEventIdAndDestination(int $eventId, string $destination): array
    {
        $table = $this->wpdb->prefix . 'pet_outbox';
        $sql = "SELECT id, event_id, destination, status, attempt_count, next_attempt_at, last_error, created_at, updated_at
                FROM $table
                WHERE event_id = %d AND destination = %s
                ORDER BY id ASC";
        $prepared = $this->wpdb->prepare($sql, [$eventId, $destination]);
        return $this->wpdb->get_results($prepared, ARRAY_A);
    }

    public function claim(array $ids, \DateTimeImmutable $claimUntil): void
    {
        if (empty($ids)) {
            return;
        }
        $table = $this->wpdb->prefix . 'pet_outbox';
        $idsSql = implode(',', array_map('intval', $ids));
        $sql = "UPDATE $table SET next_attempt_at = %s WHERE id IN ($idsSql)";
        $prepared = $this->wpdb->prepare($sql, $claimUntil->format('Y-m-d H:i:s'));
        $this->wpdb->query($prepared);
    }
}
