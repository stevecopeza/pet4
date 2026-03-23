<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Performance\Service;

use Pet\Application\Performance\Port\BenchmarkRunStateStore;
use Pet\Application\Performance\Port\PerformanceResultStore;
use Pet\Application\Performance\Port\PerformanceRunStore;
use Pet\Application\Performance\Service\PerformanceRunService;
use Pet\Application\Performance\Service\RecommendationEngine;
use Pet\Infrastructure\Performance\CacheInspectorService;
use Pet\Infrastructure\Performance\DatabaseProfilerService;
use Pet\Infrastructure\Performance\NetworkProbeService;
use Pet\Infrastructure\Performance\PhpRuntimeInspector;
use PHPUnit\Framework\TestCase;

final class PerformanceRunServiceTest extends TestCase
{
    public function testCooldownBlocksRunAndReturnsBlockedStatus(): void
    {
        $runStore = $this->createMock(PerformanceRunStore::class);
        $resultStore = $this->createMock(PerformanceResultStore::class);
        $runStateStore = $this->createMock(BenchmarkRunStateStore::class);
        $databaseProbe = $this->createMock(DatabaseProfilerService::class);
        $cacheProbe = $this->createMock(CacheInspectorService::class);
        $phpProbe = $this->createMock(PhpRuntimeInspector::class);
        $networkProbe = $this->createMock(NetworkProbeService::class);

        $runStateStore->method('getLastCompletedAt')
            ->willReturn(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $runStore->expects(self::once())
            ->method('findLatestByType')
            ->with(PerformanceRunService::RUN_TYPE_BENCHMARK)
            ->willReturn(null);
        $runStore->expects(self::once())
            ->method('createRun')
            ->with(
                PerformanceRunService::RUN_TYPE_BENCHMARK,
                PerformanceRunService::STATUS_BLOCKED_BY_COOLDOWN,
                self::isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(1001);
        $runStore->expects(self::once())
            ->method('updateRun')
            ->with(
                1001,
                PerformanceRunService::STATUS_BLOCKED_BY_COOLDOWN,
                self::isInstanceOf(\DateTimeImmutable::class),
                0
            );
        $runStateStore->expects(self::never())->method('acquireLock');
        $resultStore->expects(self::never())->method('saveMetric');
        $resultStore->expects(self::never())->method('saveMetrics');

        $service = $this->newService(
            $runStore,
            $resultStore,
            $runStateStore,
            $databaseProbe,
            $cacheProbe,
            $phpProbe,
            $networkProbe
        );

        $snapshot = $service->runBenchmark();

        self::assertSame(PerformanceRunService::STATUS_BLOCKED_BY_COOLDOWN, $snapshot->status());
        self::assertSame(1001, $snapshot->runId());
        self::assertNotNull($snapshot->cooldownRemainingSeconds());
        self::assertGreaterThan(0, (int) $snapshot->cooldownRemainingSeconds());
    }

    public function testSingleRunLockRefusalReturnsCompletedWithErrors(): void
    {
        $runStore = $this->createMock(PerformanceRunStore::class);
        $resultStore = $this->createMock(PerformanceResultStore::class);
        $runStateStore = $this->createMock(BenchmarkRunStateStore::class);
        $databaseProbe = $this->createMock(DatabaseProfilerService::class);
        $cacheProbe = $this->createMock(CacheInspectorService::class);
        $phpProbe = $this->createMock(PhpRuntimeInspector::class);
        $networkProbe = $this->createMock(NetworkProbeService::class);

        $runStateStore->method('getLastCompletedAt')->willReturn(null);
        $runStore->expects(self::once())
            ->method('createRun')
            ->with(
                PerformanceRunService::RUN_TYPE_BENCHMARK,
                PerformanceRunService::STATUS_PENDING,
                self::isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(2002);
        $runStateStore->expects(self::once())
            ->method('acquireLock')
            ->with(300)
            ->willReturn(false);
        $runStore->expects(self::once())
            ->method('updateRun')
            ->with(
                2002,
                PerformanceRunService::STATUS_COMPLETED_WITH_ERRORS,
                self::isInstanceOf(\DateTimeImmutable::class),
                self::isType('int')
            );
        $resultStore->expects(self::once())
            ->method('saveMetric')
            ->with(
                2002,
                self::stringStartsWith('error.run.lock.'),
                'unable_to_acquire_benchmark_lock',
                self::callback(static function (?array $context): bool {
                    return \is_array($context) && ($context['lock_ttl_seconds'] ?? null) === 300;
                })
            );

        $service = $this->newService(
            $runStore,
            $resultStore,
            $runStateStore,
            $databaseProbe,
            $cacheProbe,
            $phpProbe,
            $networkProbe
        );

        $snapshot = $service->runBenchmark();

        self::assertSame(PerformanceRunService::STATUS_COMPLETED_WITH_ERRORS, $snapshot->status());
        self::assertSame(1, $snapshot->errorCount());
        self::assertSame(2002, $snapshot->runId());
    }

    public function testRunLifecycleTransitionsToCompletedAndReleasesState(): void
    {
        $runStore = $this->createMock(PerformanceRunStore::class);
        $resultStore = $this->createMock(PerformanceResultStore::class);
        $runStateStore = $this->createMock(BenchmarkRunStateStore::class);
        $databaseProbe = $this->createMock(DatabaseProfilerService::class);
        $cacheProbe = $this->createMock(CacheInspectorService::class);
        $phpProbe = $this->createMock(PhpRuntimeInspector::class);
        $networkProbe = $this->createMock(NetworkProbeService::class);

        $runStateStore->method('getLastCompletedAt')->willReturn(null);
        $runStore->expects(self::once())->method('createRun')->willReturn(3003);
        $runStateStore->expects(self::once())->method('acquireLock')->with(300)->willReturn(true);
        $runStateStore->expects(self::once())->method('activateRun')->with(3003);
        $runStateStore->expects(self::once())->method('flushWorkloadMetrics')->willReturn([
            [
                'workload_key' => 'dashboard',
                'query_count' => 3,
                'execution_time_ms' => 12.5,
            ],
        ]);
        $runStateStore->expects(self::once())->method('deactivateRun');
        $runStateStore->expects(self::once())->method('releaseLock');

        $databaseProbe->expects(self::once())->method('profile')->with(2000)->willReturn([
            'metrics' => [
                [
                    'metric_key' => 'database.connection_latency_ms',
                    'metric_value' => 50.0,
                    'context' => ['timed_out' => false],
                ],
            ],
            'errors' => [],
        ]);
        $cacheProbe->expects(self::once())->method('inspect')->willReturn([
            'metrics' => [
                ['metric_key' => 'cache.backend', 'metric_value' => 'redis', 'context' => []],
                ['metric_key' => 'cache.available', 'metric_value' => true, 'context' => []],
                ['metric_key' => 'cache.stats_available', 'metric_value' => true, 'context' => []],
                ['metric_key' => 'cache.hit_rate', 'metric_value' => 95.0, 'context' => []],
            ],
            'errors' => [],
        ]);
        $phpProbe->expects(self::once())->method('inspect')->willReturn([
            'metrics' => [
                ['metric_key' => 'php.opcache_enabled', 'metric_value' => true, 'context' => []],
            ],
            'errors' => [],
        ]);
        $networkProbe->expects(self::once())->method('probe')->with(2.0, 1000)->willReturn([
            'metrics' => [
                ['metric_key' => 'network.db_ping_latency_ms', 'metric_value' => 10.0, 'context' => ['timed_out' => false]],
            ],
            'errors' => [],
        ]);

        $updateCalls = [];
        $runStore->expects(self::exactly(2))
            ->method('updateRun')
            ->willReturnCallback(static function (int $runId, string $status, ?\DateTimeImmutable $completedAt, ?int $durationMs) use (&$updateCalls): void {
                $updateCalls[] = [$runId, $status, $completedAt, $durationMs];
            });

        $resultStore->expects(self::once())
            ->method('saveMetrics')
            ->with(
                3003,
                self::callback(static function (array $rows): bool {
                    $keys = \array_column($rows, 'metric_key');
                    return \in_array('database.connection_latency_ms', $keys, true)
                        && \in_array('workload.dashboard.query_count', $keys, true)
                        && \in_array('workload.dashboard.execution_time_ms', $keys, true);
                })
            );
        $resultStore->expects(self::never())->method('saveMetric');

        $service = $this->newService(
            $runStore,
            $resultStore,
            $runStateStore,
            $databaseProbe,
            $cacheProbe,
            $phpProbe,
            $networkProbe
        );

        $snapshot = $service->runBenchmark();

        self::assertSame(PerformanceRunService::STATUS_COMPLETED, $snapshot->status());
        self::assertCount(2, $updateCalls);
        self::assertSame(PerformanceRunService::STATUS_RUNNING, $updateCalls[0][1]);
        self::assertNull($updateCalls[0][2]);
        self::assertNull($updateCalls[0][3]);
        self::assertSame(PerformanceRunService::STATUS_COMPLETED, $updateCalls[1][1]);
        self::assertInstanceOf(\DateTimeImmutable::class, $updateCalls[1][2]);
        self::assertIsInt($updateCalls[1][3]);
    }

    public function testProbeTimeoutErrorLeadsToCompletedWithErrorsAndStillPersistsMetrics(): void
    {
        $runStore = $this->createMock(PerformanceRunStore::class);
        $resultStore = $this->createMock(PerformanceResultStore::class);
        $runStateStore = $this->createMock(BenchmarkRunStateStore::class);
        $databaseProbe = $this->createMock(DatabaseProfilerService::class);
        $cacheProbe = $this->createMock(CacheInspectorService::class);
        $phpProbe = $this->createMock(PhpRuntimeInspector::class);
        $networkProbe = $this->createMock(NetworkProbeService::class);

        $runStateStore->method('getLastCompletedAt')->willReturn(null);
        $runStore->expects(self::once())->method('createRun')->willReturn(4004);
        $runStateStore->expects(self::once())->method('acquireLock')->willReturn(true);
        $runStateStore->method('flushWorkloadMetrics')->willReturn([]);
        $runStateStore->expects(self::once())->method('deactivateRun');
        $runStateStore->expects(self::once())->method('releaseLock');

        $databaseProbe->method('profile')->willReturn([
            'metrics' => [],
            'errors' => [
                [
                    'metric_key' => 'database.connection_latency_ms',
                    'message' => 'probe_timeout_exceeded',
                    'context' => ['timed_out' => true],
                ],
            ],
        ]);
        $cacheProbe->method('inspect')->willReturn([
            'metrics' => [
                ['metric_key' => 'cache.backend', 'metric_value' => 'redis', 'context' => []],
            ],
            'errors' => [],
        ]);
        $phpProbe->method('inspect')->willReturn(['metrics' => [], 'errors' => []]);
        $networkProbe->method('probe')->willReturn(['metrics' => [], 'errors' => []]);

        $updateCalls = [];
        $runStore->expects(self::exactly(2))
            ->method('updateRun')
            ->willReturnCallback(static function (int $runId, string $status, ?\DateTimeImmutable $completedAt, ?int $durationMs) use (&$updateCalls): void {
                $updateCalls[] = [$runId, $status, $completedAt, $durationMs];
            });

        $resultStore->expects(self::once())->method('saveMetrics')->with(4004, self::isType('array'));
        $resultStore->expects(self::atLeastOnce())
            ->method('saveMetric')
            ->with(
                4004,
                self::stringStartsWith('error.database.connection_latency_ms'),
                'probe_timeout_exceeded',
                self::callback(static function (?array $context): bool {
                    return \is_array($context) && ($context['timed_out'] ?? false) === true;
                })
            );

        $service = $this->newService(
            $runStore,
            $resultStore,
            $runStateStore,
            $databaseProbe,
            $cacheProbe,
            $phpProbe,
            $networkProbe
        );

        $snapshot = $service->runBenchmark();

        self::assertSame(PerformanceRunService::STATUS_COMPLETED_WITH_ERRORS, $snapshot->status());
        self::assertSame(PerformanceRunService::STATUS_RUNNING, $updateCalls[0][1]);
        self::assertSame(PerformanceRunService::STATUS_COMPLETED_WITH_ERRORS, $updateCalls[1][1]);
    }

    private function newService(
        PerformanceRunStore $runStore,
        PerformanceResultStore $resultStore,
        BenchmarkRunStateStore $runStateStore,
        DatabaseProfilerService $databaseProbe,
        CacheInspectorService $cacheProbe,
        PhpRuntimeInspector $phpProbe,
        NetworkProbeService $networkProbe
    ): PerformanceRunService {
        return new PerformanceRunService(
            $runStore,
            $resultStore,
            $runStateStore,
            $databaseProbe,
            $cacheProbe,
            $phpProbe,
            $networkProbe,
            new RecommendationEngine()
        );
    }
}

