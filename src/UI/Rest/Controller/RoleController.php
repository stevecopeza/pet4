<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\CreateRoleCommand;
use Pet\Application\Work\Command\CreateRoleHandler;
use Pet\Application\Work\Command\PublishRoleCommand;
use Pet\Application\Work\Command\PublishRoleHandler;
use Pet\Application\Work\Command\UpdateRoleCommand;
use Pet\Application\Work\Command\UpdateRoleHandler;
use Pet\Domain\Work\Repository\RoleRepository;
use Pet\Domain\Work\Repository\RoleTeamRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RoleController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'roles';

    private $roleRepository;
    private $roleTeamRepository;
    private $createRoleHandler;
    private $publishRoleHandler;
    private $updateRoleHandler;

    public function __construct(
        RoleRepository $roleRepository,
        RoleTeamRepository $roleTeamRepository,
        CreateRoleHandler $createRoleHandler,
        PublishRoleHandler $publishRoleHandler,
        UpdateRoleHandler $updateRoleHandler
    ) {
        $this->roleRepository = $roleRepository;
        $this->roleTeamRepository = $roleTeamRepository;
        $this->createRoleHandler = $createRoleHandler;
        $this->publishRoleHandler = $publishRoleHandler;
        $this->updateRoleHandler = $updateRoleHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getRoles'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createRole'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::CREATABLE, // Using POST for update
                'callback' => [$this, 'updateRole'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/publish', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'publishRole'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/teams', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getRoleTeams'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'setRoleTeams'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/owner-options', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getOwnerOptions'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getRoles(WP_REST_Request $request): WP_REST_Response
    {
        $status = $request->get_param('status');
        if ($status) {
            $roles = $this->roleRepository->findByStatus($status);
        } else {
            $roles = $this->roleRepository->findAll();
        }

        $data = array_map(function ($role) {
            $teams = $this->roleTeamRepository->findByRoleId($role->id());
            return [
                'id' => $role->id(),
                'name' => $role->name(),
                'version' => $role->version(),
                'status' => $role->status(),
                'level' => $role->level(),
                'description' => $role->description(),
                'success_criteria' => $role->successCriteria(),
                'required_skills' => $role->requiredSkills(),
                'base_internal_rate' => $role->baseInternalRate(),
                'created_at' => $role->createdAt()->format('Y-m-d H:i:s'),
                'published_at' => $role->publishedAt() ? $role->publishedAt()->format('Y-m-d H:i:s') : null,
                'teams' => $teams,
            ];
        }, $roles);

        return new WP_REST_Response($data, 200);
    }

    public function createRole(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        // Basic validation
        if (empty($params['name']) || empty($params['level'])) {
            return new WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        $command = new CreateRoleCommand(
            $params['name'],
            $params['level'],
            $params['description'] ?? '',
            $params['success_criteria'] ?? '',
            $params['required_skills'] ?? [],
            isset($params['base_internal_rate']) ? (float)$params['base_internal_rate'] : null
        );

        try {
            $this->createRoleHandler->handle($command);
            return new WP_REST_Response(['message' => 'Role created successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function updateRole(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        // Basic validation
        if (empty($params['name']) || empty($params['level'])) {
            return new WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        $command = new UpdateRoleCommand(
            $id,
            $params['name'],
            $params['level'],
            $params['description'] ?? '',
            $params['success_criteria'] ?? '',
            $params['required_skills'] ?? [],
            isset($params['base_internal_rate']) ? (float)$params['base_internal_rate'] : null
        );

        try {
            $this->updateRoleHandler->handle($command);
            return new WP_REST_Response(['message' => 'Role updated successfully'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function publishRole(WP_REST_Request $request): WP_REST_Response
    {
        $roleId = (int) $request->get_param('id');
        $command = new PublishRoleCommand($roleId);

        try {
            $this->publishRoleHandler->handle($command);
            return new WP_REST_Response(['message' => 'Role published'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function getRoleTeams(WP_REST_Request $request): WP_REST_Response
    {
        $roleId = (int) $request->get_param('id');
        $teams = $this->roleTeamRepository->findByRoleId($roleId);

        return new WP_REST_Response($teams, 200);
    }

    public function setRoleTeams(WP_REST_Request $request): WP_REST_Response
    {
        $roleId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (!isset($params['teams']) || !is_array($params['teams'])) {
            return new WP_REST_Response(['error' => 'Missing teams array'], 400);
        }

        try {
            $this->roleTeamRepository->replaceForRole($roleId, $params['teams']);
            $teams = $this->roleTeamRepository->findByRoleId($roleId);
            return new WP_REST_Response($teams, 200);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function getOwnerOptions(WP_REST_Request $request): WP_REST_Response
    {
        $roleId = (int) $request->get_param('id');

        global $wpdb;
        $teamsTable = $wpdb->prefix . 'pet_teams';
        $empTable = $wpdb->prefix . 'pet_employees';
        $assignTable = $wpdb->prefix . 'pet_person_role_assignments';

        // Recommended teams from role-team mapping
        $roleMappings = $this->roleTeamRepository->findByRoleId($roleId);
        $recommendedTeamIds = array_map(fn($m) => $m['team_id'], $roleMappings);

        // All active teams
        $allTeams = $wpdb->get_results("SELECT id, name FROM $teamsTable WHERE status = 'active' ORDER BY name ASC");

        $recommendedTeams = [];
        $otherTeams = [];
        foreach ($allTeams as $t) {
            $entry = ['id' => (int)$t->id, 'name' => $t->name];
            if (in_array((int)$t->id, $recommendedTeamIds, true)) {
                $isPrimary = false;
                foreach ($roleMappings as $m) {
                    if ($m['team_id'] === (int)$t->id && $m['is_primary']) {
                        $isPrimary = true;
                        break;
                    }
                }
                $entry['is_primary'] = $isPrimary;
                $recommendedTeams[] = $entry;
            } else {
                $otherTeams[] = $entry;
            }
        }

        // Recommended employees (holding this role)
        $recommendedEmpRows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.first_name, e.last_name
             FROM $empTable e
             INNER JOIN $assignTable a ON a.employee_id = e.id
             WHERE a.role_id = %d AND a.status = 'active' AND e.archived_at IS NULL
             ORDER BY e.first_name ASC, e.last_name ASC",
            $roleId
        ));
        $recommendedEmpIds = array_map(fn($e) => (int)$e->id, $recommendedEmpRows ?: []);
        $recommendedEmployees = array_map(fn($e) => [
            'id' => (int)$e->id,
            'name' => $e->first_name . ' ' . $e->last_name,
        ], $recommendedEmpRows ?: []);

        // All active employees not in recommended
        $allEmployees = $wpdb->get_results("SELECT id, first_name, last_name FROM $empTable WHERE archived_at IS NULL ORDER BY first_name ASC, last_name ASC");
        $otherEmployees = [];
        foreach ($allEmployees as $e) {
            if (!in_array((int)$e->id, $recommendedEmpIds, true)) {
                $otherEmployees[] = ['id' => (int)$e->id, 'name' => $e->first_name . ' ' . $e->last_name];
            }
        }

        return new WP_REST_Response([
            'recommended_teams'     => $recommendedTeams,
            'recommended_employees' => $recommendedEmployees,
            'other_teams'           => $otherTeams,
            'other_employees'       => $otherEmployees,
        ], 200);
    }
}
