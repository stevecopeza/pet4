<?php

declare(strict_types=1);

namespace Pet\Application\Performance\Service;

use Pet\Application\Performance\Dto\PerformanceMetricSet;
use Pet\Application\Performance\Dto\PerformanceRunSnapshot;
use Pet\Application\Performance\Port\BenchmarkRunStateStore;
use Pet\Application\Performance\Port\PerformanceResultStore;
use Pet\Application\Performance\Port\PerformanceRunStore;
use Pet\Infrastructure\Performance\CacheInspectorService;
use Pet\Infrastructure\Performance\DatabaseProfilerService;
use Pet\Infrastructure\Performance\NetworkProbeService;
use Pet\Infrastructure\Performance\PhpRuntimeInspector;

class PerformanceRunService
{
    public const RUN_TYPE_BENCHMARK = 'benchmark';
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BLOCKED_BY_COOLDOWN = 'blocked_by_cooldown';

    public function __construct(
        private PerformanceRunStore $runStore,
        private PerformanceResultStore $resultStore,
        private BenchmarkRunStateStore $runStateStore,
        private DatabaseProfilerService $databaseProfilerService,
        private CacheInspectorService $cacheInspectorService,
        private PhpRuntimeInspector $phpRuntimeInspector,
        private NetworkProbeService $networkProbeService,
        private RecommendationEngine $recommendationEngine,
        private int $cooldownSeconds = 300,
        private int $lockTtlSeconds = 300,
        private int $blockedCooldownDedupeWindowSeconds = 5,
        private int $databaseProbeTimeoutMs = 2000,
        private float $networkHttpTimeoutSeconds = 2.0,
        private int $networkDbPingTimeoutMs = 1000,
        private ?WorkloadSimulationService $workloadSimulationService = null
    ) {
    }

    public function runBenchmark(): PerformanceRunSnapshot
    {
        $startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $runStartedAtFloat = \microtime(true);

        $cooldownRemaining = $this->cooldownRemainingSeconds($startedAt);
        if ($cooldownRemaining > 0) {
            $existingBlocked = $this->findRecentBlockedCooldownRun($startedAt);
            if ($existingBlocked !== null) {
                return new PerformanceRunSnapshot(
                    (int) ($existingBlocked['id'] ?? $existingBlocked['run_id'] ?? 0),
                    self::RUN_TYPE_BENCHMARK,
                    self::STATUS_BLOCKED_BY_COOLDOWN,
                    $this->parseDateTime($existingBlocked['started_at'] ?? null, $startedAt),
                    $this->parseDateTime($existingBlocked['completed_at'] ?? null, $startedAt),
                    isset($existingBlocked['duration_ms']) && \is_numeric($existingBlocked['duration_ms'])
                        ? (int) $existingBlocked['duration_ms']
                        : 0,
                    0,
                    0,
                    [],
                    $cooldownRemaining
                );
            }
            $runId = $this->runStore->createRun(self::RUN_TYPE_BENCHMARK, self::STATUS_BLOCKED_BY_COOLDOWN, $startedAt);
            $this->runStore->updateRun($runId, self::STATUS_BLOCKED_BY_COOLDOWN, $startedAt, 0);
            return new PerformanceRunSnapshot(
                $runId,
                self::RUN_TYPE_BENCHMARK,
                self::STATUS_BLOCKED_BY_COOLDOWN,
                $startedAt,
                $startedAt,
                0,
                0,
                0,
                [],
                $cooldownRemaining
            );
        }

        $runId = $this->runStore->createRun(self::RUN_TYPE_BENCHMARK, self::STATUS_PENDING, $startedAt);
        $metricSet = new PerformanceMetricSet();
        $recommendations = [];
        $status = self::STATUS_FAILED;
        $completedAt = null;

        if (!$this->runStateStore->acquireLock($this->lockTtlSeconds)) {
            $completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $durationMs = $this->durationMs($runStartedAtFloat);
            $this->runStore->updateRun($runId, self::STATUS_COMPLETED_WITH_ERRORS, $completedAt, $durationMs);
            $metricSet->addError('run.lock', 'unable_to_acquire_benchmark_lock', ['lock_ttl_seconds' => $this->lockTtlSeconds]);
            $this->persistErrorsAsMetrics($runId, $metricSet->errors());
            return new PerformanceRunSnapshot(
                $runId,
                self::RUN_TYPE_BENCHMARK,
                self::STATUS_COMPLETED_WITH_ERRORS,
                $startedAt,
                $completedAt,
                $durationMs,
                0,
                \count($metricSet->errors()),
                []
            );
        }

        try {
            $this->runStateStore->activateRun($runId);
            $this->runStore->updateRun($runId, self::STATUS_RUNNING, null, null);

            $metricSet = $metricSet->merge($this->collectProbeMetricSet(
                'database',
                fn (): array => $this->databaseProfilerService->profile($this->databaseProbeTimeoutMs)
            ));
            $metricSet = $metricSet->merge($this->collectProbeMetricSet(
                'cache',
                fn (): array => $this->cacheInspectorService->inspect()
            ));
            $metricSet = $metricSet->merge($this->collectProbeMetricSet(
                'php',
                fn (): array => $this->phpRuntimeInspector->inspect()
            ));
            $metricSet = $metricSet->merge($this->collectProbeMetricSet(
                'network',
                fn (): array => $this->filterNetworkProbeForComparativeBenchmark(
                    $this->networkProbeService->probe($this->networkHttpTimeoutSeconds, $this->networkDbPingTimeoutMs)
                )
            ));

            if ($this->workloadSimulationService !== null) {
                $simulation = $this->workloadSimulationService->run();
                $warnings = isset($simulation['warnings']) && \is_array($simulation['warnings']) ? $simulation['warnings'] : [];
                foreach ($warnings as $warning) {
                    if (!\is_array($warning)) {
                        continue;
                    }
                    $issueKey = (string) ($warning['issue_key'] ?? 'workload_simulation_notice');
                    $severity = (string) ($warning['severity'] ?? 'WARNING');
                    $metricSet->addMetric(
                        'recommendation.' . $issueKey,
                        $severity,
                        ['recommendation' => $warning]
                    );
                }

                $simulationErrors = isset($simulation['errors']) && \is_array($simulation['errors']) ? $simulation['errors'] : [];
                foreach ($simulationErrors as $simulationError) {
                    if (!\is_array($simulationError)) {
                        continue;
                    }
                    $metricSet->addError(
                        (string) ($simulationError['metric_key'] ?? 'workload.simulation'),
                        (string) ($simulationError['message'] ?? 'workload_simulation_failed'),
                        isset($simulationError['context']) && \is_array($simulationError['context'])
                            ? $simulationError['context']
                            : null
                    );
                }
            }

            foreach ($this->runStateStore->flushWorkloadMetrics() as $workloadMetric) {
                $workloadKey = (string) ($workloadMetric['workload_key'] ?? '');
                if ($workloadKey === '') {
                    continue;
                }
                $metricSet->addMetric(
                    'workload.' . $workloadKey . '.query_count',
                    (int) ($workloadMetric['query_count'] ?? 0),
                    ['source' => 'benchmark_scoped']
                );
                $metricSet->addMetric(
                    'workload.' . $workloadKey . '.execution_time_ms',
                    (float) ($workloadMetric['execution_time_ms'] ?? 0.0),
                    ['source' => 'benchmark_scoped']
                );
            }

            try {
                $recommendations = $this->recommendationEngine->recommend($metricSet->metrics());
                foreach ($recommendations as $recommendation) {
                    $issueKey = (string) ($recommendation['issue_key'] ?? 'unknown_issue');
                    $severity = (string) ($recommendation['severity'] ?? 'INFO');
                    $metricSet->addMetric(
                        'recommendation.' . $issueKey,
                        $severity,
                        ['recommendation' => $recommendation]
                    );
                }
            } catch (\Throwable $e) {
                $metricSet->addError('recommendation.engine', $e->getMessage(), null);
            }

            $this->resultStore->saveMetrics($runId, $metricSet->metrics());
            $this->persistErrorsAsMetrics($runId, $metricSet->errors());

            $status = \count($metricSet->errors()) > 0
                ? self::STATUS_COMPLETED_WITH_ERRORS
                : self::STATUS_COMPLETED;
        } catch (\Throwable $e) {
            $metricSet->addError('run.execution', $e->getMessage(), null);
            $this->persistErrorsAsMetrics($runId, $metricSet->errors());
            $status = self::STATUS_FAILED;
        } finally {
            $this->runStateStore->deactivateRun();
            $this->runStateStore->releaseLock();
        }

        $completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $durationMs = $this->durationMs($runStartedAtFloat);
        $this->runStore->updateRun($runId, $status, $completedAt, $durationMs);

        return new PerformanceRunSnapshot(
            $runId,
            self::RUN_TYPE_BENCHMARK,
            $status,
            $startedAt,
            $completedAt,
            $durationMs,
            \count($metricSet->metrics()),
            \count($metricSet->errors()),
            $recommendations
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function latestBenchmarkRun(): ?array
    {
        return $this->runStore->findLatestMeaningfulByType(self::RUN_TYPE_BENCHMARK);
    }

    public function isActiveRun(): bool
    {
        return $this->runStateStore->isActive();
    }

    public function appendWorkloadMetric(string $workloadKey, int $queryCount, float $executionTimeMs): void
    {
        if (!$this->runStateStore->isActive()) {
            return;
        }
        $this->runStateStore->appendWorkloadMetric($workloadKey, $queryCount, $executionTimeMs);
    }

    private function cooldownRemainingSeconds(\DateTimeImmutable $now): int
    {
        $lastCompletedAt = $this->runStateStore->getLastCompletedAt(self::RUN_TYPE_BENCHMARK);
        if (!$lastCompletedAt instanceof \DateTimeImmutable) {
            return 0;
        }
        $elapsed = $now->getTimestamp() - $lastCompletedAt->getTimestamp();
        if ($elapsed >= $this->cooldownSeconds) {
            return 0;
        }
        return $this->cooldownSeconds - $elapsed;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) \round((\microtime(true) - $startedAt) * 1000);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findRecentBlockedCooldownRun(\DateTimeImmutable $now): ?array
    {
        $latest = $this->runStore->findLatestByType(self::RUN_TYPE_BENCHMARK);
        if (!\is_array($latest)) {
            return null;
        }

        $status = (string) ($latest['status'] ?? '');
        if ($status !== self::STATUS_BLOCKED_BY_COOLDOWN) {
            return null;
        }

        $startedAt = $this->parseDateTime($latest['started_at'] ?? null, null);
        if (!$startedAt instanceof \DateTimeImmutable) {
            return null;
        }

        $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();
        if ($elapsed > $this->blockedCooldownDedupeWindowSeconds) {
            return null;
        }

        return $latest;
    }

    private function parseDateTime($value, ?\DateTimeImmutable $fallback): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (\is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Throwable) {
                return $fallback;
            }
        }
        return $fallback;
    }

    /**
     * @param array<int, array{metric_key:string, message:string, context:array<string,mixed>|null}> $errors
     */
    private function persistErrorsAsMetrics(int $runId, array $errors): void
    {
        foreach ($errors as $index => $error) {
            $metricKey = (string) ($error['metric_key'] ?? 'run.error');
            $message = (string) ($error['message'] ?? 'unknown_error');
            $context = isset($error['context']) && \is_array($error['context']) ? $error['context'] : null;
            $this->resultStore->saveMetric(
                $runId,
                'error.' . $metricKey . '.' . $index,
                $message,
                $context
            );
        }
    }

    private function collectProbeMetricSet(string $probeName, callable $probe): PerformanceMetricSet
    {
        try {
            $result = $probe();
            if (!\is_array($result)) {
                $metricSet = new PerformanceMetricSet();
                $metricSet->addError('probe.' . $probeName, 'invalid_probe_result', ['probe' => $probeName]);
                return $metricSet;
            }
            return PerformanceMetricSet::fromProbeResult($result);
        } catch (\Throwable $e) {
            $metricSet = new PerformanceMetricSet();
            $metricSet->addError('probe.' . $probeName, $e->getMessage(), ['probe' => $probeName]);
            return $metricSet;
        }
    }

    /**
     * @param array<string,mixed> $networkProbeResult
     * @return array<string,mixed>
     */
    private function filterNetworkProbeForComparativeBenchmark(array $networkProbeResult): array
    {
        $metrics = isset($networkProbeResult['metrics']) && \is_array($networkProbeResult['metrics'])
            ? $networkProbeResult['metrics']
            : [];
        $errors = isset($networkProbeResult['errors']) && \is_array($networkProbeResult['errors'])
            ? $networkProbeResult['errors']
            : [];

        $filteredMetrics = \array_values(\array_filter($metrics, static function ($metric): bool {
            return \is_array($metric) && (string) ($metric['metric_key'] ?? '') !== 'network.http_public_latency_ms';
        }));
        $filteredErrors = \array_values(\array_filter($errors, static function ($error): bool {
            return \is_array($error) && (string) ($error['metric_key'] ?? '') !== 'network.http_public_latency_ms';
        }));

        return [
            'metrics' => $filteredMetrics,
            'errors' => $filteredErrors,
        ];
    }
}

