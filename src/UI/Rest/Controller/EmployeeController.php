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
use Pet\UI\Rest\Support\PortalPermissionHelper;
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
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createEmployee'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        // Provision endpoint: create a WP user account + employee record in one call.
        // Accepts: firstName, lastName, email, portalRole (pet_sales|pet_hr|pet_manager),
        //          hireDate, managerId, status.
        // If a WP user already exists with that email, it is reused.
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/provision', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'provisionEmployee'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateEmployee'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveEmployee'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/available-users', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAvailableUsers'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
        ]);
    }

    public function checkReadPermission(): bool
    {
        return \Pet\UI\Rest\Support\PortalPermissionHelper::check('pet_sales', 'pet_hr', 'pet_manager');
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function checkPortalPermission(): bool
    {
        return PortalPermissionHelper::check('pet_hr', 'pet_manager');
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

        // Deterministic color palette for ui-avatars.com initials
        $avatarColors = ['1a56db', '6f42c1', '0d6efd', 'e83e8c', '17a2b8', '28a745', 'fd7e14', '20c997', 'dc3545', '845ec2'];

        $data = array_map(function ($employee) use ($avatarColors) {
            $displayName = $employee->firstName() . ' ' . $employee->lastName();

            // Generate a colored initials avatar via ui-avatars.com
            $bgColor = $avatarColors[abs(crc32($displayName)) % count($avatarColors)];
            $nameParam = urlencode($displayName);
            $avatarUrl = "https://ui-avatars.com/api/?name={$nameParam}&background={$bgColor}&color=fff&size=64&bold=true";

            return [
                'id' => $employee->id(),
                'wpUserId' => $employee->wpUserId(),
                'avatarUrl' => $avatarUrl,
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
            return new WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 500);
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
            return new WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 500);
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
            return new WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 500);
        }
    }

    /**
     * provisionEmployee
     *
     * Creates (or reuses) a WP user account and links it to a new Employee record.
     * Grants the requested portal capability to the WP user.
     *
     * Required body fields: firstName, lastName, email
     * Optional: portalRole (pet_sales|pet_hr|pet_manager), hireDate, managerId, status
     */
    public function provisionEmployee(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        $firstName = trim($params['firstName'] ?? '');
        $lastName  = trim($params['lastName'] ?? '');
        $email     = sanitize_email($params['email'] ?? '');

        if (!$firstName || !$lastName || !$email) {
            return new WP_REST_Response(['message' => 'firstName, lastName and email are required.'], 400);
        }

        // ── Step 1: Resolve or create WP user ────────────────
        $existingUserId = email_exists($email);
        if ($existingUserId) {
            $wpUserId = (int) $existingUserId;
        } else {
            $username  = sanitize_user(strtolower($firstName . '.' . $lastName), true);
            $base      = $username;
            $suffix    = 1;
            while (username_exists($username)) {
                $username = $base . $suffix++;
            }

            $newUserId = wp_create_user($username, wp_generate_password(16, true, true), $email);
            if (is_wp_error($newUserId)) {
                return new WP_REST_Response(['message' => $newUserId->get_error_message()], 422);
            }

            // Set display name
            wp_update_user([
                'ID'           => $newUserId,
                'display_name' => $firstName . ' ' . $lastName,
                'first_name'   => $firstName,
                'last_name'    => $lastName,
            ]);

            $wpUserId = (int) $newUserId;
        }

        // ── Step 2: Grant portal capability ──────────────────
        $allowedCaps = ['pet_sales', 'pet_hr', 'pet_manager', 'pet_staff'];
        $portalRole  = $params['portalRole'] ?? '';
        if (in_array($portalRole, $allowedCaps, true)) {
            $wpUser = get_user_by('id', $wpUserId);
            if ($wpUser) {
                $wpUser->add_cap($portalRole);
            }
        }

        // ── Step 3: Create the Employee record ────────────────
        try {
            $command = new CreateEmployeeCommand(
                $wpUserId,
                $firstName,
                $lastName,
                $email,
                $params['status'] ?? 'active',
                !empty($params['hireDate']) ? new \DateTimeImmutable($params['hireDate']) : null,
                !empty($params['managerId']) ? (int) $params['managerId'] : null,
                $params['malleableData'] ?? [],
                $params['teamIds'] ?? []
            );

            $this->createEmployeeHandler->handle($command);

            return new WP_REST_Response([
                'message'    => 'Employee provisioned successfully.',
                'wpUserId'   => $wpUserId,
                'isNewUser'  => !$existingUserId,
                'portalRole' => $portalRole ?: null,
            ], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 500);
        }
    }
}
