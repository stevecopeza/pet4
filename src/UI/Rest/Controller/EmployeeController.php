<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Application\Identity\Command\CreateEmployeeCommand;
use Pet\Application\Identity\Command\CreateEmployeeHandler;
use Pet\Application\Identity\Command\UpdateEmployeeCommand;
use Pet\Application\Identity\Command\UpdateEmployeeHandler;
use Pet\Application\Identity\Command\ArchiveEmployeeCommand;
use Pet\Application\Identity\Command\ArchiveEmployeeHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EmployeeController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'employees';

    private EmployeeRepository $employeeRepository;
    private CreateEmployeeHandler $createEmployeeHandler;
    private UpdateEmployeeHandler $updateEmployeeHandler;
    private ArchiveEmployeeHandler $archiveEmployeeHandler;

    public function __construct(
        EmployeeRepository $employeeRepository,
        CreateEmployeeHandler $createEmployeeHandler,
        UpdateEmployeeHandler $updateEmployeeHandler,
        ArchiveEmployeeHandler $archiveEmployeeHandler
    ) {
        $this->employeeRepository = $employeeRepository;
        $this->createEmployeeHandler = $createEmployeeHandler;
        $this->updateEmployeeHandler = $updateEmployeeHandler;
        $this->archiveEmployeeHandler = $archiveEmployeeHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getEmployees'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createEmployee'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateEmployee'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveEmployee'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/available-users', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAvailableUsers'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getAvailableUsers(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $usersTable = $wpdb->users;
        $employeesTable = $wpdb->prefix . 'pet_employees';

        // Fetch users who are NOT in the employees table
        $sql = "
            SELECT u.ID, u.user_login, u.user_email, u.display_name 
            FROM $usersTable u
            LEFT JOIN $employeesTable e ON u.ID = e.wp_user_id
            WHERE e.id IS NULL
            ORDER BY u.user_login ASC
        ";

        $users = $wpdb->get_results($sql);

        return new WP_REST_Response($users, 200);
    }

    public function getEmployees(WP_REST_Request $request): WP_REST_Response
    {
        $employees = $this->employeeRepository->findAll();

        $data = array_map(function ($employee) {
            $displayName = $employee->firstName() . ' ' . $employee->lastName();

            return [
                'id' => $employee->id(),
                'wpUserId' => $employee->wpUserId(),
                'avatarUrl' => get_avatar_url($employee->wpUserId()),
                'firstName' => $employee->firstName(),
                'lastName' => $employee->lastName(),
                'displayName' => $displayName,
                'display_name' => $displayName,
                'email' => $employee->email(),
                'status' => $employee->status(),
                'hireDate' => $employee->hireDate() ? $employee->hireDate()->format('Y-m-d') : null,
                'managerId' => $employee->managerId(),
                'malleableData' => $employee->malleableData(),
                'teamIds' => $employee->teamIds(),
                'createdAt' => $employee->createdAt()->format('Y-m-d H:i:s'),
                'archivedAt' => $employee->archivedAt() ? $employee->archivedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $employees);

        return new WP_REST_Response($data, 200);
    }

    public function createEmployee(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['wpUserId']) || empty($params['firstName']) || empty($params['lastName']) || empty($params['email'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $command = new CreateEmployeeCommand(
                (int) $params['wpUserId'],
                $params['firstName'],
                $params['lastName'],
                $params['email'],
                $params['status'] ?? 'active',
                !empty($params['hireDate']) ? new \DateTimeImmutable($params['hireDate']) : null,
                !empty($params['managerId']) ? (int) $params['managerId'] : null,
                $params['malleableData'] ?? [],
                $params['teamIds'] ?? []
            );

            $this->createEmployeeHandler->handle($command);

            return new WP_REST_Response(['message' => 'Employee created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function updateEmployee(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['wpUserId']) || empty($params['firstName']) || empty($params['lastName']) || empty($params['email'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $command = new UpdateEmployeeCommand(
                $id,
                (int) $params['wpUserId'],
                $params['firstName'],
                $params['lastName'],
                $params['email'],
                $params['status'] ?? 'active',
                !empty($params['hireDate']) ? new \DateTimeImmutable($params['hireDate']) : null,
                !empty($params['managerId']) ? (int) $params['managerId'] : null,
                $params['malleableData'] ?? [],
                $params['teamIds'] ?? []
            );

            $this->updateEmployeeHandler->handle($command);

            return new WP_REST_Response(['message' => 'Employee updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function archiveEmployee(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new ArchiveEmployeeCommand($id);
            $this->archiveEmployeeHandler->handle($command);

            return new WP_REST_Response(['message' => 'Employee archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }
}
