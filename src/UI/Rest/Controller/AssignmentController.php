<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\AssignRoleToPersonCommand;
use Pet\Application\Work\Command\AssignRoleToPersonHandler;
use Pet\Application\Work\Command\EndAssignmentCommand;
use Pet\Application\Work\Command\EndAssignmentHandler;
use Pet\Domain\Work\Repository\AssignmentRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class AssignmentController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'assignments';

    private $assignmentRepository;
    private $assignRoleToPersonHandler;
    private $endAssignmentHandler;

    public function __construct(
        AssignmentRepository $assignmentRepository,
        AssignRoleToPersonHandler $assignRoleToPersonHandler,
        EndAssignmentHandler $endAssignmentHandler
    ) {
        $this->assignmentRepository = $assignmentRepository;
        $this->assignRoleToPersonHandler = $assignRoleToPersonHandler;
        $this->endAssignmentHandler = $endAssignmentHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAssignments'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createAssignment'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/end', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'endAssignment'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getAssignments(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = $request->get_param('employee_id');
        $roleId = $request->get_param('role_id');

        if ($employeeId) {
            $assignments = $this->assignmentRepository->findByEmployeeId((int) $employeeId);
        } elseif ($roleId) {
            $assignments = $this->assignmentRepository->findByRoleId((int) $roleId);
        } else {
            // Allow fetching all assignments if no filter provided (admin view)
            $assignments = $this->assignmentRepository->findAll();
        }

        $data = array_map(function ($assignment) {
            return [
                'id' => $assignment->id(),
                'employee_id' => $assignment->employeeId(),
                'role_id' => $assignment->roleId(),
                'start_date' => $assignment->startDate()->format('Y-m-d'),
                'end_date' => $assignment->endDate() ? $assignment->endDate()->format('Y-m-d') : null,
                'allocation_pct' => $assignment->allocationPct(),
                'status' => $assignment->status(),
                'created_at' => $assignment->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $assignments);

        return new WP_REST_Response($data, 200);
    }

    public function createAssignment(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['employee_id']) || empty($params['role_id']) || empty($params['start_date'])) {
            return new WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        $command = new AssignRoleToPersonCommand(
            (int) $params['employee_id'],
            (int) $params['role_id'],
            $params['start_date'],
            (int) ($params['allocation_pct'] ?? 100)
        );

        try {
            $assignmentId = $this->assignRoleToPersonHandler->handle($command);
            return new WP_REST_Response(['id' => $assignmentId, 'message' => 'Assignment created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function endAssignment(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['end_date'])) {
            return new WP_REST_Response(['error' => 'Missing end_date'], 400);
        }

        $command = new EndAssignmentCommand($id, $params['end_date']);

        try {
            $this->endAssignmentHandler->handle($command);
            return new WP_REST_Response(['message' => 'Assignment ended'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }
}
