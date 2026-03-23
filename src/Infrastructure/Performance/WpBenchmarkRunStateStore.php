<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Performance;

use Pet\Application\Performance\Port\BenchmarkRunStateStore;

class WpBenchmarkRunStateStore implements BenchmarkRunStateStore
{
    private const LOCK_KEY = 'pet_performance_benchmark_lock';
    private const ACTIVE_RUN_KEY = 'pet_performance_active_run_id';
    private const METRICS_KEY_PREFIX = 'pet_performance_workload_metrics_';
    private const COMPLETED_STATUSES = [
        'completed',
        'completed_with_errors',
        'failed',
    ];

    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function acquireLock(int $ttlSeconds): bool
    {
        $existing = \get_transient(self::LOCK_KEY);
        if ($existing !== false) {
            return false;
        }

        \set_transient(self::LOCK_KEY, 1, $ttlSeconds);
        return true;
    }

    public function releaseLock(): void
    {
        \delete_transient(self::LOCK_KEY);
    }

    public function isActive(): bool
    {
        return $this->getActiveRunId() !== null;
    }

    public function getActiveRunId(): ?int
    {
        $value = \get_transient(self::ACTIVE_RUN_KEY);
        if ($value === false || $value === null || !\is_numeric($value)) {
            return null;
        }

        $runId = (int) $value;
        return $runId > 0 ? $runId : null;
    }

    public function activateRun(int $runId): void
    {
        \set_transient(self::ACTIVE_RUN_KEY, $runId, 10 * MINUTE_IN_SECONDS);
        \delete_transient(self::METRICS_KEY_PREFIX . $runId);
    }

    public function deactivateRun(): void
    {
        $activeRunId = $this->getActiveRunId();
        \delete_transient(self::ACTIVE_RUN_KEY);
        if ($activeRunId !== null) {
            \delete_transient(self::METRICS_KEY_PREFIX . $activeRunId);
        }
    }

    public function getLastCompletedAt(string $runType): ?\DateTimeImmutable
    {
        $table = $this->wpdb->prefix . 'pet_performance_runs';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT completed_at, started_at
                 FROM {$table}
                 WHERE run_type = %s
                   AND status IN (%s, %s, %s)
                 ORDER BY COALESCE(completed_at, started_at) DESC
                 LIMIT 1",
                $runType,
                self::COMPLETED_STATUSES[0],
                self::COMPLETED_STATUSES[1],
                self::COMPLETED_STATUSES[2]
            ),
            ARRAY_A
        );

        if (!\is_array($row)) {
            return null;
        }

        $value = isset($row['completed_at']) && \is_string($row['completed_at']) && $row['completed_at'] !== ''
            ? $row['completed_at']
            : (isset($row['started_at']) && \is_string($row['started_at']) ? $row['started_at'] : null);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    public function appendWorkloadMetric(string $workloadKey, int $queryCount, float $executionTimeMs): void
    {
        $runId = $this->getActiveRunId();
        if ($runId === null) {
            return;
        }

        $key = self::METRICS_KEY_PREFIX . $runId;
        $existing = \get_transient($key);
        $rows = \is_array($existing) ? $existing : [];
        $rows[] = [
            'workload_key' => $workloadKey,
            'query_count' => $queryCount,
            'execution_time_ms' => $executionTimeMs,
        ];
        \set_transient($key, $rows, 10 * MINUTE_IN_SECONDS);
    }

    /**
     * @return array<int, array{workload_key:string, query_count:int, execution_time_ms:float}>
     */
    public function flushWorkloadMetrics(): array
    {
        $runId = $this->getActiveRunId();
        if ($runId === null) {
            return [];
        }

        $key = self::METRICS_KEY_PREFIX . $runId;
        $existing = \get_transient($key);
        \delete_transient($key);
        if (!\is_array($existing)) {
            return [];
        }

        return \array_map(function (array $row): array {
            return [
                'workload_key' => (string) ($row['workload_key'] ?? ''),
                'query_count' => isset($row['query_count']) && \is_numeric($row['query_count']) ? (int) $row['query_count'] : 0,
                'execution_time_ms' => isset($row['execution_time_ms']) && \is_numeric($row['execution_time_ms']) ? (float) $row['execution_time_ms'] : 0.0,
            ];
        }, $existing);
    }
}

