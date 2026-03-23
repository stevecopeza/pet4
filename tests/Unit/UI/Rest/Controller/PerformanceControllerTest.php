<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller {
    function register_rest_route(string $namespace, string $route, array $args): bool
    {
        $GLOBALS['pet_test_registered_routes'][] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
        ];
        return true;
    }

    function current_user_can(string $capability): bool
    {
        return (bool) ($GLOBALS['pet_test_current_user_can'] ?? true);
    }
}

namespace Pet\Tests\Unit\UI\Rest\Controller {

    use Pet\Application\Performance\Dto\PerformanceRunSnapshot;
    use Pet\Application\Performance\Port\PerformanceResultStore;
    use Pet\Application\Performance\Service\PerformanceRunService;
    use Pet\UI\Rest\Controller\PerformanceController;
    use PHPUnit\Framework\TestCase;

    final class PerformanceControllerTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['pet_test_registered_routes'] = [];
            $GLOBALS['pet_test_current_user_can'] = true;
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['pet_test_registered_routes'], $GLOBALS['pet_test_current_user_can']);
        }

        public function testRegisterRoutesIncludesPermissionCallbacks(): void
        {
            $runService = $this->createMock(PerformanceRunService::class);
            $resultStore = $this->createMock(PerformanceResultStore::class);
            $controller = new PerformanceController($runService, $resultStore);

            $controller->registerRoutes();

            self::assertCount(2, $GLOBALS['pet_test_registered_routes']);

            $routes = [];
            foreach ($GLOBALS['pet_test_registered_routes'] as $entry) {
                $routes[$entry['route']] = $entry;
            }

            self::assertArrayHasKey('/performance/latest', $routes);
            self::assertArrayHasKey('/performance/run', $routes);
            self::assertIsCallable($routes['/performance/latest']['args'][0]['permission_callback']);
            self::assertIsCallable($routes['/performance/run']['args'][0]['permission_callback']);

            $GLOBALS['pet_test_current_user_can'] = false;
            self::assertFalse(($routes['/performance/latest']['args'][0]['permission_callback'])());
            self::assertFalse(($routes['/performance/run']['args'][0]['permission_callback'])());
        }

        public function testRunReturnsStructuredResponsePayload(): void
        {
            $runService = $this->createMock(PerformanceRunService::class);
            $resultStore = $this->createMock(PerformanceResultStore::class);

            $snapshot = new PerformanceRunSnapshot(
                77,
                PerformanceRunService::RUN_TYPE_BENCHMARK,
                PerformanceRunService::STATUS_COMPLETED_WITH_ERRORS,
                new \DateTimeImmutable('2026-03-23T08:00:00+00:00'),
                new \DateTimeImmutable('2026-03-23T08:00:05+00:00'),
                5000,
                5,
                1,
                []
            );

            $runService->expects(self::once())->method('runBenchmark')->willReturn($snapshot);
            $resultStore->expects(self::once())->method('findByRunId')->with(77)->willReturn([
                [
                    'id' => 1,
                    'run_id' => 77,
                    'metric_key' => 'database.connection_latency_ms',
                    'metric_value' => 82.1,
                    'context' => ['timed_out' => false],
                ],
                [
                    'id' => 2,
                    'run_id' => 77,
                    'metric_key' => 'workload.dashboard.query_count',
                    'metric_value' => 3,
                    'context' => ['source' => 'benchmark_scoped'],
                ],
                [
                    'id' => 3,
                    'run_id' => 77,
                    'metric_key' => 'workload.dashboard.execution_time_ms',
                    'metric_value' => 15.5,
                    'context' => ['source' => 'benchmark_scoped'],
                ],
                [
                    'id' => 4,
                    'run_id' => 77,
                    'metric_key' => 'recommendation.low_cache_hit_rate',
                    'metric_value' => 'WARNING',
                    'context' => [
                        'recommendation' => [
                            'issue_key' => 'low_cache_hit_rate',
                            'severity' => 'WARNING',
                            'title' => 'Cache hit rate is below target',
                            'message' => 'Review cache invalidation behaviour.',
                        ],
                    ],
                ],
                [
                    'id' => 5,
                    'run_id' => 77,
                    'metric_key' => 'error.database.connection_latency_ms.0',
                    'metric_value' => 'probe_timeout_exceeded',
                    'context' => ['timed_out' => true],
                ],
            ]);

            $controller = new PerformanceController($runService, $resultStore);
            $request = new \WP_REST_Request('POST', '/pet/v1/performance/run');
            $response = $controller->run($request);

            self::assertSame(200, $response->get_status());
            $data = $response->get_data();

            self::assertSame('completed_with_errors', $data['run']['status'] ?? null);
            self::assertArrayHasKey('metrics', $data);
            self::assertArrayHasKey('probe', $data['metrics']);
            self::assertArrayHasKey('workload', $data['metrics']);
            self::assertArrayHasKey('recommendations', $data['metrics']);
            self::assertArrayHasKey('errors', $data['metrics']);

            self::assertSame(3, $data['metrics']['workload']['dashboard']['query_count'] ?? null);
            self::assertSame(15.5, $data['metrics']['workload']['dashboard']['execution_time_ms'] ?? null);
            self::assertSame('low_cache_hit_rate', $data['metrics']['recommendations'][0]['issue_key'] ?? null);
            self::assertSame('probe_timeout_exceeded', $data['metrics']['errors'][0]['message'] ?? null);
        }
    }
}

