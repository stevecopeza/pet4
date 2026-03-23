<?php

declare(strict_types=1);

namespace Pet\Application\Performance\Service;

final class WorkloadSimulationService
{
    private const REPORT_TYPE = 'customer_advisory_summary';

    public function __construct(
        private \wpdb $wpdb,
        private int $iterations = 3
    ) {
    }

    /**
     * @return array{
     *   warnings:array<int,array{
     *     issue_key:string,
     *     severity:string,
     *     title:string,
     *     message:string,
     *     metric_key:string|null,
     *     observed_value:mixed,
     *     context:array<string,mixed>
     *   }>,
     *   errors:array<int,array{
     *     metric_key:string,
     *     message:string,
     *     context:array<string,mixed>|null
     *   }>
     * }
     */
    public function run(): array
    {
        $warnings = [];
        $errors = [];

        if (!\function_exists('rest_do_request') || !\class_exists('\\WP_REST_Request')) {
            $warnings[] = $this->warning(
                'workload_simulation_unavailable',
                'REST request simulator is unavailable in this runtime; workload self-check skipped.',
                ['reason' => 'rest_runtime_unavailable']
            );

            return [
                'warnings' => $warnings,
                'errors' => $errors,
            ];
        }

        $customerId = $this->resolveCustomerId();
        $workItemId = $this->resolveWorkItemId();
        $reportId = $customerId !== null ? $this->resolveLatestReportId($customerId) : null;
        $deferReportGetScenario = false;
        $advisorySignalsAvailable = $this->routeExists('/pet/v1/advisory/signals/recent');
        $advisoryReportsAvailable = $this->routeExists('/pet/v1/advisory/reports')
            && $this->routeExists('/pet/v1/advisory/reports/latest')
            && $this->routeExists('/pet/v1/advisory/reports/generate');
        $advisoryReportItemRouteAvailable = $this->routePrefixExists('/pet/v1/advisory/reports/');

        $scenarios = [
            $this->scenario('dashboard', 'GET', '/pet/v1/dashboard'),
            $this->scenario('ticket.list', 'GET', '/pet/v1/tickets'),
        ];

        if ($advisorySignalsAvailable) {
            $scenarios[] = $this->scenario('advisory.signals', 'GET', '/pet/v1/advisory/signals/recent', ['limit' => 20]);
        } else {
            $warnings[] = $this->warning(
                'workload_simulation_unavailable',
                'Advisory signal routes are unavailable; skipped advisory signal workload scenarios.',
                ['workload_key' => 'advisory.signals.*', 'reason' => 'route_unavailable']
            );
        }

        if (!$advisorySignalsAvailable) {
            $warnings[] = $this->warning(
                'workload_simulation_unavailable',
                'Advisory signal work-item route is unavailable; skipped advisory.signals_work_item workload scenario.',
                ['workload_key' => 'advisory.signals_work_item', 'reason' => 'route_unavailable']
            );
        } elseif ($workItemId === null) {
            $warnings[] = $this->warning(
                'workload_simulation_insufficient_data',
                'No work items found; skipped advisory.signals_work_item workload scenario.',
                ['workload_key' => 'advisory.signals_work_item', 'required_data' => 'work_item_id']
            );
        } else {
            $scenarios[] = $this->scenario('advisory.signals_work_item', 'GET', '/pet/v1/advisory/signals/work-item/' . $workItemId);
        }

        if (!$advisoryReportsAvailable) {
            $warnings[] = $this->warning(
                'workload_simulation_unavailable',
                'Advisory report routes are unavailable; skipped advisory report workload scenarios.',
                ['workload_key' => 'advisory.reports.*', 'reason' => 'route_unavailable']
            );
        } elseif ($customerId === null) {
            $warnings[] = $this->warning(
                'workload_simulation_insufficient_data',
                'No customers found; skipped advisory report workload scenarios.',
                ['workload_key' => 'advisory.reports.*', 'required_data' => 'customer_id']
            );
        } else {
            $scenarios[] = $this->scenario('advisory.reports_list', 'GET', '/pet/v1/advisory/reports', ['customer_id' => $customerId]);
            $scenarios[] = $this->scenario('advisory.reports_latest', 'GET', '/pet/v1/advisory/reports/latest', ['customer_id' => $customerId]);
            $scenarios[] = $this->scenario(
                'advisory.reports_generate',
                'POST',
                '/pet/v1/advisory/reports/generate',
                [],
                ['customerId' => $customerId, 'reportType' => self::REPORT_TYPE],
                1
            );
        }

        if (!$advisoryReportsAvailable || !$advisoryReportItemRouteAvailable) {
            $warnings[] = $this->warning(
                'workload_simulation_unavailable',
                'Advisory report detail route is unavailable; skipped advisory.reports_get workload scenario.',
                ['workload_key' => 'advisory.reports_get', 'reason' => 'route_unavailable']
            );
        } elseif ($reportId !== null) {
            $scenarios[] = $this->scenario('advisory.reports_get', 'GET', '/pet/v1/advisory/reports/' . $reportId);
        } elseif ($customerId !== null) {
            $deferReportGetScenario = true;
        } else {
            $warnings[] = $this->warning(
                'workload_simulation_insufficient_data',
                'No advisory reports found; skipped advisory.reports_get workload scenario.',
                ['workload_key' => 'advisory.reports_get', 'required_data' => 'advisory_report_id']
            );
        }

        foreach ($scenarios as $scenario) {
            $result = $this->runScenario($scenario);
            if (isset($result['warning']) && \is_array($result['warning'])) {
                $warnings[] = $result['warning'];
            }
            if (isset($result['error']) && \is_array($result['error'])) {
                $errors[] = $result['error'];
            }
        }

        if ($deferReportGetScenario && $customerId !== null) {
            $resolvedReportId = $this->resolveLatestReportId($customerId);
            if ($resolvedReportId === null) {
                $warnings[] = $this->warning(
                    'workload_simulation_insufficient_data',
                    'No advisory reports found; skipped advisory.reports_get workload scenario.',
                    ['workload_key' => 'advisory.reports_get', 'required_data' => 'advisory_report_id']
                );
            } else {
                $result = $this->runScenario(
                    $this->scenario('advisory.reports_get', 'GET', '/pet/v1/advisory/reports/' . $resolvedReportId)
                );
                if (isset($result['warning']) && \is_array($result['warning'])) {
                    $warnings[] = $result['warning'];
                }
                if (isset($result['error']) && \is_array($result['error'])) {
                    $errors[] = $result['error'];
                }
            }
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $jsonBody
     * @return array{
     *   workload_key:string,
     *   method:string,
     *   route:string,
     *   query:array<string,mixed>,
     *   json_body:array<string,mixed>,
     *   iterations:int
     * }
     */
    private function scenario(
        string $workloadKey,
        string $method,
        string $route,
        array $query = [],
        array $jsonBody = [],
        ?int $iterations = null
    ): array {
        return [
            'workload_key' => $workloadKey,
            'method' => $method,
            'route' => $route,
            'query' => $query,
            'json_body' => $jsonBody,
            'iterations' => $iterations ?? $this->iterations,
        ];
    }

    /**
     * @param array{
     *   workload_key:string,
     *   method:string,
     *   route:string,
     *   query:array<string,mixed>,
     *   json_body:array<string,mixed>,
     *   iterations:int
     * } $scenario
     * @return array{
     *   warning?:array{
     *     issue_key:string,
     *     severity:string,
     *     title:string,
     *     message:string,
     *     metric_key:string|null,
     *     observed_value:mixed,
     *     context:array<string,mixed>
     *   },
     *   error?:array{
     *     metric_key:string,
     *     message:string,
     *     context:array<string,mixed>|null
     *   }
     * }
     */
    private function runScenario(array $scenario): array
    {
        $iterations = \max(1, (int) ($scenario['iterations'] ?? 1));
        for ($iteration = 1; $iteration <= $iterations; $iteration++) {
            $dispatch = $this->dispatch(
                (string) $scenario['method'],
                (string) $scenario['route'],
                $scenario['query'],
                $scenario['json_body']
            );

            if (($dispatch['ok'] ?? false) === true) {
                continue;
            }

            $message = (string) ($dispatch['message'] ?? 'workload_simulation_request_failed');
            $statusCode = isset($dispatch['status_code']) && \is_numeric($dispatch['status_code'])
                ? (int) $dispatch['status_code']
                : null;
            $responseCode = isset($dispatch['response_code']) && \is_string($dispatch['response_code'])
                ? $dispatch['response_code']
                : null;

            if (!empty($dispatch['fatal'])) {
                return [
                    'error' => [
                        'metric_key' => 'simulation.' . (string) $scenario['workload_key'],
                        'message' => $message,
                        'context' => [
                            'workload_key' => (string) $scenario['workload_key'],
                            'route' => (string) $scenario['route'],
                            'iteration' => $iteration,
                            'status_code' => $statusCode,
                            'response_code' => $responseCode,
                        ],
                    ],
                ];
            }

            $issueKey = 'workload_simulation_partial';
            if ($responseCode === 'rest_no_route') {
                $issueKey = 'workload_simulation_unavailable';
            } elseif (
                $statusCode === 401
                || $statusCode === 403
                || $responseCode === 'rest_forbidden'
                || $responseCode === 'rest_cannot_view'
                || $responseCode === 'rest_cannot_create'
            ) {
                $issueKey = 'workload_simulation_permission';
            }

            return [
                'warning' => $this->warning(
                    $issueKey,
                    $this->buildScenarioFailureMessage($issueKey, $scenario, $statusCode, $responseCode, $message),
                    [
                        'workload_key' => (string) $scenario['workload_key'],
                        'route' => (string) $scenario['route'],
                        'iteration' => $iteration,
                        'status_code' => $statusCode,
                        'response_code' => $responseCode,
                        'response_message' => $message,
                    ]
                ),
            ];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $jsonBody
     * @return array{
     *   ok:bool,
     *   status_code?:int,
     *   response_code?:string,
     *   message?:string,
     *   fatal?:bool
     * }
     */
    private function dispatch(string $method, string $route, array $query, array $jsonBody): array
    {
        try {
            $request = new \WP_REST_Request($method, $route);
            if (!empty($query)) {
                $request->set_query_params($query);
            }

            if (!empty($jsonBody) && \strtoupper($method) !== 'GET') {
                if (\method_exists($request, 'set_json_params')) {
                    $request->set_json_params($jsonBody);
                } else {
                    $request->set_body_params($jsonBody);
                }
            }

            $response = \rest_do_request($request);
            if (\is_wp_error($response)) {
                return [
                    'ok' => false,
                    'response_code' => (string) $response->get_error_code(),
                    'message' => (string) $response->get_error_message(),
                    'fatal' => false,
                ];
            }

            $statusCode = \method_exists($response, 'get_status') ? (int) $response->get_status() : 500;
            if ($statusCode >= 200 && $statusCode < 300) {
                return ['ok' => true, 'status_code' => $statusCode];
            }

            $responseCode = null;
            $responseMessage = 'rest_request_failed';
            if (\method_exists($response, 'get_data')) {
                $data = $response->get_data();
                if (\is_array($data)) {
                    $responseCode = isset($data['code']) && \is_string($data['code']) ? $data['code'] : null;
                    $responseMessage = isset($data['message']) && \is_string($data['message'])
                        ? $data['message']
                        : $responseMessage;
                }
            }

            return [
                'ok' => false,
                'status_code' => $statusCode,
                'response_code' => $responseCode,
                'message' => $responseMessage,
                'fatal' => false,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'fatal' => true,
            ];
        }
    }

    private function resolveCustomerId(): ?int
    {
        $table = $this->wpdb->prefix . 'pet_customers';
        $customerId = $this->wpdb->get_var(
            "SELECT id FROM {$table} WHERE archived_at IS NULL ORDER BY id ASC LIMIT 1"
        );
        if (!\is_numeric($customerId)) {
            return null;
        }
        $id = (int) $customerId;
        return $id > 0 ? $id : null;
    }

    private function resolveWorkItemId(): ?string
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        $workItemId = $this->wpdb->get_var(
            "SELECT id FROM {$table} WHERE status IN ('active','waiting','pending') ORDER BY updated_at DESC LIMIT 1"
        );
        if (!\is_string($workItemId) || $workItemId === '') {
            return null;
        }
        return $workItemId;
    }

    private function resolveLatestReportId(int $customerId): ?string
    {
        $table = $this->wpdb->prefix . 'pet_advisory_reports';
        $reportId = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE scope_type = %s AND scope_id = %d
                 ORDER BY generated_at DESC, version_number DESC
                 LIMIT 1",
                'customer',
                $customerId
            )
        );

        if (!\is_string($reportId) || $reportId === '') {
            return null;
        }
        return $reportId;
    }

    private function routeExists(string $route): bool
    {
        $routes = $this->registeredRoutes();
        if ($routes === null) {
            return true;
        }
        return isset($routes[$route]);
    }

    private function routePrefixExists(string $routePrefix): bool
    {
        $routes = $this->registeredRoutes();
        if ($routes === null) {
            return true;
        }

        foreach ($routes as $route => $_callbacks) {
            if (\is_string($route) && \str_starts_with($route, $routePrefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function registeredRoutes(): ?array
    {
        if (!\function_exists('rest_get_server')) {
            return null;
        }

        $server = \rest_get_server();
        if (!\is_object($server) || !\method_exists($server, 'get_routes')) {
            return null;
        }

        $routes = $server->get_routes();
        return \is_array($routes) ? $routes : null;
    }

    /**
     * @param array{
     *   workload_key:string,
     *   method:string,
     *   route:string,
     *   query:array<string,mixed>,
     *   json_body:array<string,mixed>,
     *   iterations:int
     * } $scenario
     */
    private function buildScenarioFailureMessage(
        string $issueKey,
        array $scenario,
        ?int $statusCode,
        ?string $responseCode,
        string $responseMessage
    ): string {
        $workloadKey = (string) ($scenario['workload_key'] ?? 'unknown');
        if ($issueKey === 'workload_simulation_unavailable') {
            return 'Skipped workload scenario "' . $workloadKey . '" because the REST endpoint is unavailable.';
        }
        if ($issueKey === 'workload_simulation_permission') {
            return 'Skipped workload scenario "' . $workloadKey . '" due to REST permission denial.';
        }

        $details = [];
        if ($statusCode !== null) {
            $details[] = 'HTTP ' . $statusCode;
        }
        if ($responseCode !== null && $responseCode !== '') {
            $details[] = $responseCode;
        }
        if ($responseMessage !== '') {
            $details[] = $responseMessage;
        }
        $detailSuffix = $details === [] ? '' : ' (' . \implode('; ', $details) . ')';

        return 'Workload simulation request for "' . $workloadKey . '" did not succeed' . $detailSuffix . '; scenario metrics may be incomplete.';
    }

    /**
     * @param array<string,mixed> $context
     * @return array{
     *   issue_key:string,
     *   severity:string,
     *   title:string,
     *   message:string,
     *   metric_key:string|null,
     *   observed_value:mixed,
     *   context:array<string,mixed>
     * }
     */
    private function warning(string $issueKey, string $message, array $context): array
    {
        return [
            'issue_key' => $issueKey,
            'severity' => 'WARNING',
            'title' => 'Benchmark self-check notice',
            'message' => $message,
            'metric_key' => isset($context['workload_key']) && \is_string($context['workload_key'])
                ? $context['workload_key']
                : null,
            'observed_value' => null,
            'context' => $context,
        ];
    }
}

