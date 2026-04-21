<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Advisory\Command\GenerateAdvisoryReportCommand;
use Pet\Application\Advisory\Command\GenerateAdvisoryReportHandler;
use Pet\Application\Advisory\Service\CustomerAdvisoryAccessService;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Advisory\Repository\AdvisoryReportRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class AdvisoryReportController implements RestController
{
    private const NAMESPACE = 'pet/v1';

    public function __construct(
        private FeatureFlagService $featureFlags,
        private AdvisoryReportRepository $reports,
        private GenerateAdvisoryReportHandler $generateHandler,
        private CustomerAdvisoryAccessService $access
    ) {
    }

    public function registerRoutes(): void
    {
        if (!$this->featureFlags->isAdvisoryReportsEnabled()) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/advisory/reports', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listReports'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'customer_id' => ['required' => true, 'type' => 'integer'],
                    'report_type' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/advisory/reports/latest', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'latestReport'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'customer_id' => ['required' => true, 'type' => 'integer'],
                    'report_type' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/advisory/reports/(?P<id>[a-zA-Z0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getReport'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/advisory/reports/generate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generateReport'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'customerId' => ['required' => true, 'type' => 'integer'],
                    'reportType' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return is_user_logged_in() && current_user_can('edit_posts');
    }

    public function listReports(WP_REST_Request $request): WP_REST_Response
    {
        $profileToken = $this->beginBenchmarkWorkloadProfile('advisory.reports_list');
        try {
            $customerId = (int)$request->get_param('customer_id');
            $reportType = (string)($request->get_param('report_type') ?? 'customer_advisory_summary');

            if (!$this->canAccessCustomer($customerId)) {
                return new WP_REST_Response(['message' => 'Forbidden'], 403);
            }

            $reports = $this->reports->findByScope($reportType, GenerateAdvisoryReportHandler::SCOPE_TYPE_CUSTOMER, $customerId, 50);
            $data = array_map(fn($r) => $this->serializeReportList($r), $reports);
            return new WP_REST_Response($data, 200);
        } finally {
            $this->endBenchmarkWorkloadProfile($profileToken);
        }
    }

    public function latestReport(WP_REST_Request $request): WP_REST_Response
    {
        $profileToken = $this->beginBenchmarkWorkloadProfile('advisory.reports_latest');
        try {
            $customerId = (int)$request->get_param('customer_id');
            $reportType = (string)($request->get_param('report_type') ?? 'customer_advisory_summary');

            if (!$this->canAccessCustomer($customerId)) {
                return new WP_REST_Response(['message' => 'Forbidden'], 403);
            }

            $report = $this->reports->findLatestByScope($reportType, GenerateAdvisoryReportHandler::SCOPE_TYPE_CUSTOMER, $customerId);
            if (!$report) {
                return new WP_REST_Response(['message' => 'Not Found'], 404);
            }
            return new WP_REST_Response($this->serializeReportDetail($report), 200);
        } finally {
            $this->endBenchmarkWorkloadProfile($profileToken);
        }
    }

    public function getReport(WP_REST_Request $request): WP_REST_Response
    {
        $profileToken = $this->beginBenchmarkWorkloadProfile('advisory.reports_get');
        try {
            $id = (string)$request->get_param('id');
            $report = $this->reports->findById($id);
            if (!$report) {
                return new WP_REST_Response(['message' => 'Not Found'], 404);
            }

            if ($report->scopeType() !== GenerateAdvisoryReportHandler::SCOPE_TYPE_CUSTOMER) {
                return new WP_REST_Response(['message' => 'Forbidden'], 403);
            }

            if (!$this->canAccessCustomer($report->scopeId())) {
                return new WP_REST_Response(['message' => 'Forbidden'], 403);
            }

            return new WP_REST_Response($this->serializeReportDetail($report), 200);
        } finally {
            $this->endBenchmarkWorkloadProfile($profileToken);
        }
    }

    public function generateReport(WP_REST_Request $request): WP_REST_Response
    {
        $profileToken = $this->beginBenchmarkWorkloadProfile('advisory.reports_generate');
        try {
            if (!$this->featureFlags->isAdvisoryReportsEnabled()) {
                return new WP_REST_Response(['message' => 'Advisory reports disabled'], 403);
            }

            $params = $request->get_json_params() ?: [];
            $customerId = (int)($params['customerId'] ?? $request->get_param('customerId'));
            $reportType = (string)($params['reportType'] ?? $request->get_param('reportType') ?? 'customer_advisory_summary');

            if ($customerId <= 0) {
                return new WP_REST_Response(['message' => 'Invalid customerId'], 400);
            }

            if (!$this->canAccessCustomer($customerId)) {
                return new WP_REST_Response(['message' => 'Forbidden'], 403);
            }

            try {
                $generatedBy = (int)get_current_user_id();
                $report = $this->generateHandler->handle(new GenerateAdvisoryReportCommand($customerId, $reportType, $generatedBy));
                return new WP_REST_Response($this->serializeReportDetail($report), 201);
            } catch (\Throwable $e) {
                return new WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
            }
        } finally {
            $this->endBenchmarkWorkloadProfile($profileToken);
        }
    }

    private function canAccessCustomer(int $customerId): bool
    {
        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');
        return $this->access->canAccessCustomer($wpUserId, $customerId, $isAdmin);
    }

    private function serializeReportList($report): array
    {
        return [
            'id' => $report->id(),
            'report_type' => $report->reportType(),
            'scope_type' => $report->scopeType(),
            'scope_id' => $report->scopeId(),
            'version_number' => $report->versionNumber(),
            'title' => $report->title(),
            'summary' => $report->summary(),
            'status' => $report->status(),
            'generated_at' => $report->generatedAt()->format('Y-m-d H:i:s'),
            'generated_by' => $report->generatedBy(),
        ];
    }

    private function serializeReportDetail($report): array
    {
        $data = $this->serializeReportList($report);
        $data['content'] = $report->content();
        $data['source_snapshot_metadata'] = $report->sourceSnapshotMetadata();
        return $data;
    }

    /**
     * @return array{run_id:int, workload_key:string, query_count_start:int, started_at:float}|null
     */
    private function beginBenchmarkWorkloadProfile(string $workloadKey): ?array
    {
        $activeRunId = $this->activeBenchmarkRunId();
        if ($activeRunId === null) {
            return null;
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return null;
        }

        return [
            'run_id' => $activeRunId,
            'workload_key' => $workloadKey,
            'query_count_start' => $this->queryCount($wpdb),
            'started_at' => microtime(true),
        ];
    }

    /**
     * @param array{run_id:int, workload_key:string, query_count_start:int, started_at:float}|null $token
     */
    private function endBenchmarkWorkloadProfile(?array $token): void
    {
        if ($token === null) {
            return;
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return;
        }

        $queryDelta = $this->queryCount($wpdb) - (int) $token['query_count_start'];
        if ($queryDelta < 0) {
            $queryDelta = 0;
        }

        $payload = [
            'workload_key' => (string) $token['workload_key'],
            'query_count' => $queryDelta,
            'execution_time_ms' => round((microtime(true) - (float) $token['started_at']) * 1000, 3),
        ];

        $metricsKey = 'pet_performance_workload_metrics_' . (int) $token['run_id'];
        $existing = get_transient($metricsKey);
        $rows = is_array($existing) ? $existing : [];
        $rows[] = $payload;
        set_transient($metricsKey, $rows, 10 * MINUTE_IN_SECONDS);
    }

    private function activeBenchmarkRunId(): ?int
    {
        $value = get_transient('pet_performance_active_run_id');
        if ($value === false || $value === null || !is_numeric($value)) {
            return null;
        }

        $runId = (int) $value;
        return $runId > 0 ? $runId : null;
    }

    private function queryCount(\wpdb $wpdb): int
    {
        if (property_exists($wpdb, 'num_queries') && is_numeric($wpdb->num_queries)) {
            return (int) $wpdb->num_queries;
        }
        if (defined('SAVEQUERIES') && SAVEQUERIES && property_exists($wpdb, 'queries') && is_array($wpdb->queries)) {
            return count($wpdb->queries);
        }
        return 0;
    }
}

