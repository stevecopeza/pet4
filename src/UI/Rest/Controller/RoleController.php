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
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RoleController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'roles';

    private $roleRepository;
    private $createRoleHandler;
    private $publishRoleHandler;
    private $updateRoleHandler;

    public function __construct(
        RoleRepository $roleRepository,
        CreateRoleHandler $createRoleHandler,
        PublishRoleHandler $publishRoleHandler,
        UpdateRoleHandler $updateRoleHandler
    ) {
        $this->roleRepository = $roleRepository;
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
            return [
                'id' => $role->id(),
                'name' => $role->name(),
                'version' => $role->version(),
                'status' => $role->status(),
                'level' => $role->level(),
                'description' => $role->description(),
                'success_criteria' => $role->successCriteria(),
                'required_skills' => $role->requiredSkills(),
                'created_at' => $role->createdAt()->format('Y-m-d H:i:s'),
                'published_at' => $role->publishedAt() ? $role->publishedAt()->format('Y-m-d H:i:s') : null,
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
            $params['required_skills'] ?? []
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
            $params['required_skills'] ?? []
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
}
