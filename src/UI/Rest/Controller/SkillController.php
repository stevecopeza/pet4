<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\CreateSkillCommand;
use Pet\Application\Work\Command\CreateSkillHandler;
use Pet\Application\Work\Command\UpdateSkillCommand;
use Pet\Application\Work\Command\UpdateSkillHandler;
use Pet\Domain\Work\Repository\SkillRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SkillController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'skills';

    private $skillRepository;
    private $createSkillHandler;
    private $updateSkillHandler;

    public function __construct(
        SkillRepository $skillRepository,
        CreateSkillHandler $createSkillHandler,
        UpdateSkillHandler $updateSkillHandler
    ) {
        $this->skillRepository = $skillRepository;
        $this->createSkillHandler = $createSkillHandler;
        $this->updateSkillHandler = $updateSkillHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSkills'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createSkill'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateSkill'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getSkills(WP_REST_Request $request): WP_REST_Response
    {
        $skills = $this->skillRepository->findAll();

        $data = array_map(function ($skill) {
            return [
                'id' => $skill->id(),
                'name' => $skill->name(),
                'capability_id' => $skill->capabilityId(),
                'description' => $skill->description(),
            ];
        }, $skills);

        return new WP_REST_Response($data, 200);
    }

    public function createSkill(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['capability_id']) || empty($params['description'])) {
            return new WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        $command = new CreateSkillCommand(
            $params['name'],
            (int) $params['capability_id'],
            $params['description']
        );

        try {
            $this->createSkillHandler->handle($command);
            return new WP_REST_Response(['message' => 'Skill created successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function updateSkill(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['capability_id']) || empty($params['description'])) {
            return new WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        $command = new UpdateSkillCommand(
            $id,
            $params['name'],
            (int) $params['capability_id'],
            $params['description']
        );

        try {
            $this->updateSkillHandler->handle($command);
            return new WP_REST_Response(['message' => 'Skill updated successfully'], 200);
        } catch (\RuntimeException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }
}
