<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Infrastructure\System\Service\LogService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class LogController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'logs';

    private LogService $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getLogs'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/download', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'downloadReport'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getLogs(WP_REST_Request $request): WP_REST_Response
    {
        $type = $request->get_param('type');
        $lines = (int)($request->get_param('lines') ?? 200);

        $filter = ($type === 'pet') ? '[PET' : null;

        $logs = $this->logService->getRecentEntries($lines, $filter);

        return new WP_REST_Response(['logs' => $logs], 200);
    }

    public function downloadReport(WP_REST_Request $request): WP_REST_Response
    {
        $report = $this->logService->generateDiagnosticReport();

        // In REST context, we return the content. The frontend will handle the file download blob.
        return new WP_REST_Response([
            'content' => $report,
            'filename' => 'pet-diagnostic-report-' . date('Y-m-d-His') . '.txt'
        ], 200);
    }
}
