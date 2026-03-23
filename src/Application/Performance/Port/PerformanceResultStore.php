<?php

declare(strict_types=1);

namespace Pet\Application\Performance\Port;

interface PerformanceResultStore
{
    /**
     * @param array<string,mixed>|null $context
     */
    public function saveMetric(int $runId, string $metricKey, $metricValue, ?array $context = null): void;

    /**
     * @param array<int, array{metric_key:string, metric_value:mixed, context:array<string,mixed>|null}> $rows
     */
    public function saveMetrics(int $runId, array $rows): void;

    /**
     * @return array<int, array{id:int, run_id:int, metric_key:string, metric_value:mixed, context:array<string,mixed>|null}>
     */
    public function findByRunId(int $runId): array;
}

