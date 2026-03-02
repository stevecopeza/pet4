<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\GeneratePersonKpisCommand;
use Pet\Application\Work\Command\GeneratePersonKpisHandler;
use Pet\Application\Work\Command\UpdatePersonKpiCommand;
use Pet\Application\Work\Command\UpdatePersonKpiHandler;
use Pet\Domain\Work\Repository\PersonKpiRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class PersonKpiController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    
    private PersonKpiRepository $personKpiRepository;
    private GeneratePersonKpisHandler $generatePersonKpisHandler;
    private UpdatePersonKpiHandler $updatePersonKpiHandler;

    public function __construct(
        PersonKpiRepository $personKpiRepository,
        GeneratePersonKpisHandler $generatePersonKpisHandler,
        UpdatePersonKpiHandler $updatePersonKpiHandler
    ) {
        $this->personKpiRepository = $personKpiRepository;
        $this->generatePersonKpisHandler = $generatePersonKpisHandler;
        $this->updatePersonKpiHandler = $updatePersonKpiHandler;
    }

    public function registerRoutes(): void
    {
        // Get KPIs for an employee
        register_rest_route(self::NAMESPACE, '/employees/(?P<id>\d+)/kpis', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getPersonKpis'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generatePersonKpis'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // Update a specific KPI instance (score/actual)
        register_rest_route(self::NAMESPACE, '/kpis/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updatePersonKpi'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function getPersonKpis(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int) $request->get_param('id');
        $periodStart = $request->get_param('period_start');
        $periodEnd = $request->get_param('period_end');

        if ($periodStart && $periodEnd) {
            $start = new \DateTimeImmutable($periodStart);
            $end = new \DateTimeImmutable($periodEnd);
            $kpis = $this->personKpiRepository->findByEmployeeAndPeriod($employeeId, $start, $end);
        } else {
            $kpis = $this->personKpiRepository->findByEmployeeId($employeeId);
        }
        
        $data = array_map(function ($kpi) {
            return [
                'id' => $kpi->id(),
                'employee_id' => $kpi->employeeId(),
                'kpi_definition_id' => $kpi->kpiDefinitionId(),
                'role_id' => $kpi->roleId(),
                'period_start' => $kpi->periodStart()->format('Y-m-d'),
                'period_end' => $kpi->periodEnd()->format('Y-m-d'),
                'target_value' => $kpi->targetValue(),
                'actual_value' => $kpi->actualValue(),
                'score' => $kpi->score(),
                'status' => $kpi->status(),
                'created_at' => $kpi->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $kpis);

        return new WP_REST_Response($data, 200);
    }

    public function generatePersonKpis(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['role_id']) || empty($params['period_start']) || empty($params['period_end'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        $command = new GeneratePersonKpisCommand(
            $employeeId,
            (int) $params['role_id'],
            $params['period_start'],
            $params['period_end']
        );

        try {
            $this->generatePersonKpisHandler->handle($command);
            return new WP_REST_Response(['message' => 'Person KPIs generated successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function updatePersonKpi(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (!isset($params['actual_value']) || !isset($params['score'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        $command = new UpdatePersonKpiCommand(
            $id,
            (float) $params['actual_value'],
            (float) $params['score']
        );

        try {
            $this->updatePersonKpiHandler->handle($command);
            return new WP_REST_Response(['message' => 'Person KPI updated successfully'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
