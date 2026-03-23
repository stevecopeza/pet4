<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Application\Performance\Port\PerformanceRunStore;

class SqlPerformanceRunStore implements PerformanceRunStore
{
    private \wpdb $wpdb;
    private string $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_performance_runs';
    }

    public function createRun(string $runType, string $status, \DateTimeImmutable $startedAt): int
    {
        $this->wpdb->insert($this->tableName, [
            'run_type' => $runType,
            'status' => $status,
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
            'completed_at' => null,
            'duration_ms' => null,
        ]);

        return (int) $this->wpdb->insert_id;
    }

    public function updateRun(int $runId, string $status, ?\DateTimeImmutable $completedAt, ?int $durationMs): void
    {
        $this->wpdb->update(
            $this->tableName,
            [
                'status' => $status,
                'completed_at' => $completedAt?->format('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
            ],
            [
                'id' => $runId,
            ]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findLatestByType(string $runType): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, run_type, status, started_at, completed_at, duration_ms
                 FROM {$this->tableName}
                 WHERE run_type = %s
                 ORDER BY id DESC
                 LIMIT 1",
                $runType
            ),
            ARRAY_A
        );

        return \is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findLatestMeaningfulByType(string $runType): ?array
    {
        $preferred = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, run_type, status, started_at, completed_at, duration_ms
                 FROM {$this->tableName}
                 WHERE run_type = %s
                   AND status IN (%s, %s)
                 ORDER BY id DESC
                 LIMIT 1",
                $runType,
                'completed',
                'completed_with_errors'
            ),
            ARRAY_A
        );

        if (\is_array($preferred)) {
            $preferred['selection_policy'] = 'latest_completed_or_completed_with_errors';
            $preferred['fallback_to_failed'] = false;
            return $preferred;
        }

        $failedFallback = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, run_type, status, started_at, completed_at, duration_ms
                 FROM {$this->tableName}
                 WHERE run_type = %s
                   AND status = %s
                 ORDER BY id DESC
                 LIMIT 1",
                $runType,
                'failed'
            ),
            ARRAY_A
        );

        if (!\is_array($failedFallback)) {
            return null;
        }

        $failedFallback['selection_policy'] = 'fallback_failed_no_completed_result';
        $failedFallback['fallback_to_failed'] = true;
        return $failedFallback;
    }
}

