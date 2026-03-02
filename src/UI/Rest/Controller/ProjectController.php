<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Delivery\Command\AddTaskCommand;
use Pet\Application\Delivery\Command\AddTaskHandler;
use Pet\Application\Delivery\Command\CreateProjectCommand;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Application\Delivery\Command\UpdateProjectCommand;
use Pet\Application\Delivery\Command\UpdateProjectHandler;
use Pet\Application\Delivery\Command\ArchiveProjectCommand;
use Pet\Application\Delivery\Command\ArchiveProjectHandler;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ProjectController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'projects';

    private ProjectRepository $projectRepository;
    private CreateProjectHandler $createProjectHandler;
    private AddTaskHandler $addTaskHandler;
    private UpdateProjectHandler $updateProjectHandler;
    private ArchiveProjectHandler $archiveProjectHandler;

    public function __construct(
        ProjectRepository $projectRepository,
        CreateProjectHandler $createProjectHandler,
        AddTaskHandler $addTaskHandler,
        UpdateProjectHandler $updateProjectHandler,
        ArchiveProjectHandler $archiveProjectHandler
    ) {
        $this->projectRepository = $projectRepository;
        $this->createProjectHandler = $createProjectHandler;
        $this->addTaskHandler = $addTaskHandler;
        $this->updateProjectHandler = $updateProjectHandler;
        $this->archiveProjectHandler = $archiveProjectHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getProjects'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createProject'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getProject'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateProject'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveProject'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/tasks', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addTask'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getProjects(WP_REST_Request $request): WP_REST_Response
    {
        $customerId = $request->get_param('customer_id');
        
        if ($customerId) {
            $projects = $this->projectRepository->findByCustomerId((int) $customerId);
        } else {
            $projects = $this->projectRepository->findAll();
        }

        $data = array_map(function ($project) {
            return $this->serializeProject($project);
        }, $projects);

        return new WP_REST_Response($data, 200);
    }

    public function getProject(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $project = $this->projectRepository->findById($id);

        if (!$project) {
            return new WP_REST_Response(['error' => 'Project not found'], 404);
        }

        return new WP_REST_Response($this->serializeProject($project), 200);
    }

    private function serializeProject($project): array
    {
        return [
            'id' => $project->id(),
            'name' => $project->name(),
            'customerId' => $project->customerId(),
            'soldHours' => $project->soldHours(),
            'state' => $project->state()->toString(),
            'soldValue' => $project->soldValue(),
            'startDate' => $project->startDate() ? $project->startDate()->format('Y-m-d') : null,
            'endDate' => $project->endDate() ? $project->endDate()->format('Y-m-d') : null,
            'malleableData' => $project->malleableData(),
            'archivedAt' => $project->archivedAt() ? $project->archivedAt()->format('Y-m-d H:i:s') : null,
            'tasks' => array_map(function ($task) {
                return [
                    'id' => $task->id(),
                    'name' => $task->name(),
                    'estimatedHours' => $task->estimatedHours(),
                    'completed' => $task->isCompleted(),
                ];
            }, $project->tasks()),
        ];
    }

    public function createProject(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateProjectCommand(
                (int) $params['customerId'],
                $params['name'],
                (float) $params['soldHours'],
                isset($params['sourceQuoteId']) ? (int) $params['sourceQuoteId'] : null,
                isset($params['soldValue']) ? (float) $params['soldValue'] : 0.00,
                !empty($params['startDate']) ? new \DateTimeImmutable($params['startDate']) : null,
                !empty($params['endDate']) ? new \DateTimeImmutable($params['endDate']) : null,
                isset($params['malleableData']) ? (array) $params['malleableData'] : []
            );

            $this->createProjectHandler->handle($command);

            return new WP_REST_Response(['message' => 'Project created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function updateProject(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['status'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $malleableData = $params['malleableData'] ?? [];

            $command = new UpdateProjectCommand(
                $id,
                $params['name'],
                $params['status'],
                !empty($params['startDate']) ? new \DateTimeImmutable($params['startDate']) : null,
                !empty($params['endDate']) ? new \DateTimeImmutable($params['endDate']) : null,
                $malleableData
            );

            $this->updateProjectHandler->handle($command);

            return new WP_REST_Response(['message' => 'Project updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function archiveProject(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new ArchiveProjectCommand($id);
            $this->archiveProjectHandler->handle($command);

            return new WP_REST_Response(['message' => 'Project archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addTask(WP_REST_Request $request): WP_REST_Response
    {
        $projectId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new AddTaskCommand(
                $projectId,
                $params['name'],
                (float) $params['estimatedHours']
            );

            $this->addTaskHandler->handle($command);

            return new WP_REST_Response(['message' => 'Task added'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
