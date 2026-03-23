<?php

declare(strict_types=1);

namespace Pet\Application\Performance\Port;

interface BenchmarkRunStateStore
{
    public function acquireLock(int $ttlSeconds): bool;

    public function releaseLock(): void;

    public function isActive(): bool;

    public function getActiveRunId(): ?int;

    public function activateRun(int $runId): void;

    public function deactivateRun(): void;

    public function getLastCompletedAt(string $runType): ?\DateTimeImmutable;

    public function appendWorkloadMetric(string $workloadKey, int $queryCount, float $executionTimeMs): void;

    /**
     * @return array<int, array{workload_key:string, query_count:int, execution_time_ms:float}>
     */
    public function flushWorkloadMetrics(): array;
}

