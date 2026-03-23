<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Performance\Dto\PerformanceRunSnapshot;
use Pet\Application\Performance\Port\PerformanceResultStore;
use Pet\Application\Performance\Service\PerformanceRunService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class PerformanceController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const WORKLOAD_CONTRACT_KEYS = [
        'dashboard',
        'advisory.signals',
        'advisory.signals_work_item',
        'advisory.reports_list',
        'advisory.reports_latest',
        'advisory.reports_get',
        'advisory.reports_generate',
        'ticket.list',
    ];

    public function __construct(
        private PerformanceRunService $runService,
        private PerformanceResultStore $resultStore
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/performance/latest', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'latest'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/performance/run', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'run'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function latest(WP_REST_Request $request): WP_REST_Response
    {
        $run = $this->runService->latestBenchmarkRun();
        if (!\is_array($run)) {
            return new WP_REST_Response([
                'run' => null,
                'metrics' => [
                    'probe' => [],
                    'workload' => $this->emptyWorkloadContract(),
                    'workload_other' => [],
                    'recommendations' => [],
                    'errors' => [],
                ],
                'counts' => [
                    'probe' => 0,
                    'recommendations' => 0,
                    'errors' => 0,
                ],
            ], 200);
        }

        $runId = isset($run['id']) && \is_numeric($run['id']) ? (int) $run['id'] : 0;
        $metrics = $runId > 0 ? $this->resultStore->findByRunId($runId) : [];

        return new WP_REST_Response($this->buildResponsePayload($run, $metrics), 200);
    }

    public function run(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $snapshot = $this->runService->runBenchmark();
            $runId = $snapshot->runId();
            $metrics = $runId > 0 ? $this->resultStore->findByRunId($runId) : [];
            $run = $this->runArrayFromSnapshot($snapshot);
            $payload = $this->buildResponsePayload($run, $metrics);
            $payload['snapshot'] = $snapshot->toArray();
            return new WP_REST_Response($payload, 200);
        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'error' => [
                    'code' => 'PERFORMANCE_RUN_FAILED',
                    'message' => 'Failed to run performance benchmark',
                ],
            ], 500);
        }
    }

    /**
     * @param array<string,mixed> $run
     * @param array<int, array{id:int, run_id:int, metric_key:string, metric_value:mixed, context:array<string,mixed>|null}> $metrics
     * @return array<string,mixed>
     */
    private function buildResponsePayload(array $run, array $metrics): array
    {
        $probe = [];
        $workload = $this->emptyWorkloadContract();
        $workloadOther = [];
        $recommendations = [];
        $errors = [];

        foreach ($metrics as $metric) {
            $metricKey = (string) ($metric['metric_key'] ?? '');
            $metricValue = $metric['metric_value'] ?? null;
            $context = isset($metric['context']) && \is_array($metric['context']) ? $metric['context'] : null;
            if ($metricKey === '') {
                continue;
            }

            if (\str_starts_with($metricKey, 'workload.')) {
                $this->accumulateWorkloadMetric($metricKey, $metricValue, $workload, $workloadOther);
                continue;
            }

            if (\str_starts_with($metricKey, 'recommendation.')) {
                $recommendation = $context['recommendation'] ?? null;
                if (\is_array($recommendation)) {
                    $recommendations[] = $recommendation;
                } else {
                    $recommendations[] = [
                        'issue_key' => \substr($metricKey, \strlen('recommendation.')),
                        'severity' => \is_string($metricValue) ? $metricValue : null,
                    ];
                }
                continue;
            }

            if (\str_starts_with($metricKey, 'error.')) {
                $errors[] = [
                    'metric_key' => $metricKey,
                    'message' => \is_string($metricValue) ? $metricValue : \wp_json_encode($metricValue),
                    'context' => $context,
                ];
                continue;
            }

            $probe[$metricKey] = [
                'value' => $metricValue,
                'context' => $context,
            ];
        }

        return [
            'run' => $this->normalizeRun($run),
            'metrics' => [
                'probe' => $probe,
                'workload' => $workload,
                'workload_other' => $workloadOther,
                'recommendations' => $recommendations,
                'errors' => $errors,
            ],
            'counts' => [
                'probe' => \count($probe),
                'recommendations' => \count($recommendations),
                'errors' => \count($errors),
            ],
        ];
    }

    /**
     * @return array<string, array{query_count:int, execution_time_ms:float}>
     */
    private function emptyWorkloadContract(): array
    {
        $workload = [];
        foreach (self::WORKLOAD_CONTRACT_KEYS as $key) {
            $workload[$key] = [
                'query_count' => 0,
                'execution_time_ms' => 0.0,
            ];
        }
        return $workload;
    }

    /**
     * @param array<string, array{query_count:int, execution_time_ms:float}> $workload
     * @param array<string, array{query_count:int, execution_time_ms:float}> $workloadOther
     */
    private function accumulateWorkloadMetric(string $metricKey, $metricValue, array &$workload, array &$workloadOther): void
    {
        if (!\preg_match('/^workload\.(.+)\.(query_count|execution_time_ms)$/', $metricKey, $matches)) {
            return;
        }

        $workloadKey = (string) ($matches[1] ?? '');
        $field = (string) ($matches[2] ?? '');
        if ($workloadKey === '' || $field === '') {
            return;
        }

        $target = \in_array($workloadKey, self::WORKLOAD_CONTRACT_KEYS, true) ? 'contract' : 'other';
        if ($target === 'contract') {
            if ($field === 'query_count') {
                $workload[$workloadKey]['query_count'] += \is_numeric($metricValue) ? (int) $metricValue : 0;
            } else {
                $workload[$workloadKey]['execution_time_ms'] += \is_numeric($metricValue) ? (float) $metricValue : 0.0;
            }
            return;
        }

        if (!isset($workloadOther[$workloadKey])) {
            $workloadOther[$workloadKey] = [
                'query_count' => 0,
                'execution_time_ms' => 0.0,
            ];
        }

        if ($field === 'query_count') {
            $workloadOther[$workloadKey]['query_count'] += \is_numeric($metricValue) ? (int) $metricValue : 0;
        } else {
            $workloadOther[$workloadKey]['execution_time_ms'] += \is_numeric($metricValue) ? (float) $metricValue : 0.0;
        }
    }

    /**
     * @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    private function normalizeRun(array $run): array
    {
        $normalized = [
            'id' => isset($run['id']) && \is_numeric($run['id']) ? (int) $run['id'] : (isset($run['run_id']) ? (int) $run['run_id'] : 0),
            'run_type' => (string) ($run['run_type'] ?? PerformanceRunService::RUN_TYPE_BENCHMARK),
            'status' => (string) ($run['status'] ?? ''),
            'started_at' => $this->toIso8601($run['started_at'] ?? null),
            'completed_at' => $this->toIso8601($run['completed_at'] ?? null),
            'duration_ms' => isset($run['duration_ms']) && \is_numeric($run['duration_ms']) ? (int) $run['duration_ms'] : null,
        ];

        if (isset($run['selection_policy']) || \array_key_exists('fallback_to_failed', $run)) {
            $normalized['selection'] = [
                'policy' => isset($run['selection_policy']) ? (string) $run['selection_policy'] : 'unspecified',
                'fallback_to_failed' => (bool) ($run['fallback_to_failed'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>
     */
    private function runArrayFromSnapshot(PerformanceRunSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->runId(),
            'run_type' => $snapshot->runType(),
            'status' => $snapshot->status(),
            'started_at' => $snapshot->startedAt()->format('c'),
            'completed_at' => $snapshot->completedAt()?->format('c'),
            'duration_ms' => $snapshot->durationMs(),
        ];
    }

    /**
     * @param mixed $value
     */
    private function toIso8601($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }
        if (!\is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('c');
        } catch (\Throwable) {
            return null;
        }
    }
}

