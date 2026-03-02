<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\AssignKpiToRoleCommand;
use Pet\Application\Work\Command\AssignKpiToRoleHandler;
use Pet\Domain\Work\Repository\RoleKpiRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RoleKpiController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'roles'; // Sub-resource: /roles/{id}/kpis

    private RoleKpiRepository $roleKpiRepository;
    private AssignKpiToRoleHandler $assignKpiToRoleHandler;

    public function __construct(
        RoleKpiRepository $roleKpiRepository,
        AssignKpiToRoleHandler $assignKpiToRoleHandler
    ) {
        $this->roleKpiRepository = $roleKpiRepository;
        $this->assignKpiToRoleHandler = $assignKpiToRoleHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/kpis', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getRoleKpis'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'assignKpiToRole'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function getRoleKpis(WP_REST_Request $request): WP_REST_Response
    {
        $roleId = (int) $request->get_param('id');
        $kpis = $this->roleKpiRepository->findByRoleId($roleId);
        
        $data = array_map(function ($kpi) {
            return [
                'id' => $kpi->id(),
                'role_id' => $kpi->roleId(),
                'kpi_definition_id' => $kpi->kpiDefinitionId(),
                'weight_percentage' => $kpi->weightPercentage(),
                'target_value' => $kpi->targetValue(),
                'measurement_frequency' => $kpi->measurementFrequency(),
                'created_at' => $kpi->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $kpis);

        return new WP_REST_Response($data, 200);
    }

    public function assignKpiToRole(WP_REST_Request $request): WP_REST_Response
    {
        $roleId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['kpi_definition_id']) || !isset($params['weight_percentage']) || !isset($params['target_value'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        $command = new AssignKpiToRoleCommand(
            $roleId,
            (int) $params['kpi_definition_id'],
            (int) $params['weight_percentage'],
            (float) $params['target_value'],
            $params['measurement_frequency'] ?? 'monthly'
        );

        try {
            $this->assignKpiToRoleHandler->handle($command);
            return new WP_REST_Response(['message' => 'KPI assigned to role successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
