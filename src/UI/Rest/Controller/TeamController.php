<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Application\Team\Command\CreateTeamCommand;
use Pet\Application\Team\Command\CreateTeamHandler;
use Pet\Application\Team\Command\UpdateTeamCommand;
use Pet\Application\Team\Command\UpdateTeamHandler;
use Pet\Application\Team\Command\ArchiveTeamCommand;
use Pet\Application\Team\Command\ArchiveTeamHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class TeamController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'teams';

    private TeamRepository $teamRepository;
    private CreateTeamHandler $createTeamHandler;
    private UpdateTeamHandler $updateTeamHandler;
    private ArchiveTeamHandler $archiveTeamHandler;

    public function __construct(
        TeamRepository $teamRepository,
        CreateTeamHandler $createTeamHandler,
        UpdateTeamHandler $updateTeamHandler,
        ArchiveTeamHandler $archiveTeamHandler
    ) {
        $this->teamRepository = $teamRepository;
        $this->createTeamHandler = $createTeamHandler;
        $this->updateTeamHandler = $updateTeamHandler;
        $this->archiveTeamHandler = $archiveTeamHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getTeams'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createTeam'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getTeam'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateTeam'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/archive', [
            [
                'methods' => WP_REST_Server::CREATABLE, // POST to archive
                'callback' => [$this, 'archiveTeam'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getTeams(WP_REST_Request $request): WP_REST_Response
    {
        $includeArchived = $request->get_param('include_archived') === 'true';
        $teams = $this->teamRepository->findAll($includeArchived);

        // Build hierarchy
        $tree = $this->buildTree($teams);

        return new WP_REST_Response($tree, 200);
    }

    public function getTeam(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $team = $this->teamRepository->find($id);

        if (!$team) {
            return new WP_REST_Response(['code' => 'not_found', 'message' => 'Team not found'], 404);
        }

        return new WP_REST_Response($this->serializeTeam($team), 200);
    }

    public function createTeam(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        $command = new CreateTeamCommand(
            $params['name'] ?? '',
            !empty($params['parent_team_id']) ? (int) $params['parent_team_id'] : null,
            !empty($params['manager_id']) ? (int) $params['manager_id'] : null,
            !empty($params['escalation_manager_id']) ? (int) $params['escalation_manager_id'] : null,
            $params['status'] ?? 'active',
            $params['visual']['type'] ?? null,
            $params['visual']['ref'] ?? null,
            $params['member_ids'] ?? []
        );

        try {
            $this->createTeamHandler->handle($command);
            return new WP_REST_Response(['status' => 'created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['code' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function updateTeam(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $command = new UpdateTeamCommand(
            $id,
            $params['name'] ?? '',
            !empty($params['parent_team_id']) ? (int) $params['parent_team_id'] : null,
            !empty($params['manager_id']) ? (int) $params['manager_id'] : null,
            !empty($params['escalation_manager_id']) ? (int) $params['escalation_manager_id'] : null,
            $params['status'] ?? 'active',
            $params['visual']['type'] ?? null,
            $params['visual']['ref'] ?? null,
            $params['member_ids'] ?? []
        );

        try {
            $this->updateTeamHandler->handle($command);
            return new WP_REST_Response(['status' => 'updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['code' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function archiveTeam(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $command = new ArchiveTeamCommand($id);

        try {
            $this->archiveTeamHandler->handle($command);
            return new WP_REST_Response(['status' => 'archived'], 200);
        } catch (\DomainException $e) {
             return new WP_REST_Response(['code' => 'conflict', 'message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            return new WP_REST_Response(['code' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    private function buildTree(array $teams): array
    {
        $teamMap = [];
        $roots = [];

        // First pass: Index by ID and serialize
        foreach ($teams as $team) {
            $data = $this->serializeTeam($team);
            $data['children'] = [];
            $teamMap[$team->id()] = $data;
        }

        // Second pass: Build tree
        foreach ($teamMap as $id => &$teamData) {
            $parentId = $teamData['parent_team_id'];
            if ($parentId && isset($teamMap[$parentId])) {
                $teamMap[$parentId]['children'][] = &$teamData;
            } else {
                $roots[] = &$teamData;
            }
        }

        return $roots;
    }

    private function serializeTeam($team): array
    {
        return [
            'id' => $team->id(),
            'name' => $team->name(),
            'parent_team_id' => $team->parentTeamId(),
            'manager_id' => $team->managerId(),
            'escalation_manager_id' => $team->escalationManagerId(),
            'status' => $team->status(),
            'visual' => [
                'type' => $team->visualType(),
                'ref' => $team->visualRef(),
                'version' => $team->visualVersion(),
            ],
            'member_ids' => $team->memberIds(),
            'created_at' => $team->createdAt()->format('Y-m-d H:i:s'),
        ];
    }
}
