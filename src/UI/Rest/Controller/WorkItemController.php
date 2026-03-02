<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\AssignWorkItemCommand;
use Pet\Application\Work\Command\AssignWorkItemHandler;
use Pet\Application\Work\Command\OverrideWorkItemPriorityCommand;
use Pet\Application\Work\Command\OverrideWorkItemPriorityHandler;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class WorkItemController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'work-items';

    public function __construct(
        private WorkItemRepository $repository,
        private AdvisorySignalRepository $signalRepository,
        private AssignWorkItemHandler $assignHandler,
        private OverrideWorkItemPriorityHandler $overrideHandler
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getItems'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/by-source', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getBySource'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>[a-zA-Z0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>[a-zA-Z0-9-]+)/assign', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'assignItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>[a-zA-Z0-9-]+)/prioritize', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'prioritizeItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);
    }

    public function getItems(WP_REST_Request $request): WP_REST_Response
    {
        $userId = $request->get_param('assigned_user_id');
        $deptId = $request->get_param('department_id');
        $unassigned = $request->get_param('unassigned');

        if ($userId) {
            $items = $this->repository->findByAssignedUser($userId);
        } elseif ($deptId && $unassigned) {
            $items = $this->repository->findByDepartmentUnassigned($deptId);
        } else {
            // Default to active items
            $items = $this->repository->findActive();
        }

        $data = array_map(fn($item) => $this->serializeWorkItem($item), $items);

        return new WP_REST_Response($data, 200);
    }

    public function getItem(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $item = $this->repository->findById($id);

        if (!$item) {
            return new WP_REST_Response(['message' => 'Not Found'], 404);
        }

        return new WP_REST_Response($this->serializeWorkItem($item), 200);
    }

    public function getBySource(WP_REST_Request $request): WP_REST_Response
    {
        $sourceType = (string)$request->get_param('source_type');
        $sourceId = (string)$request->get_param('source_id');

        if ($sourceType === '' || $sourceId === '') {
            return new WP_REST_Response(['message' => 'Missing source_type or source_id'], 400);
        }

        $allowedTypes = ['ticket', 'escalation', 'admin'];
        if (!in_array($sourceType, $allowedTypes, true)) {
            return new WP_REST_Response(['message' => 'Invalid source_type'], 400);
        }

        $item = $this->repository->findBySource($sourceType, $sourceId);

        if (!$item) {
            return new WP_REST_Response(['message' => 'Not Found'], 404);
        }

        return new WP_REST_Response($this->serializeWorkItem($item), 200);
    }

    public function assignItem(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $userId = $request->get_param('assigned_user_id');

        if (!$userId) {
            return new WP_REST_Response(['message' => 'Missing assigned_user_id'], 400);
        }

        try {
            $command = new AssignWorkItemCommand($id, $userId);
            $this->assignHandler->handle($command);
            return new WP_REST_Response(['message' => 'Assigned'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    public function prioritizeItem(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $override = $request->get_param('override_value');

        if (!is_numeric($override)) {
            return new WP_REST_Response(['message' => 'Missing or invalid override_value'], 400);
        }

        try {
            $command = new OverrideWorkItemPriorityCommand($id, (float)$override);
            $this->overrideHandler->handle($command);
            return new WP_REST_Response(['message' => 'Priority Override Applied'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    private function serializeWorkItem($item): array
    {
        $signals = $this->signalRepository->findActiveByWorkItemId($item->getId());

        return [
            'id' => $item->getId(),
            'source_type' => $item->getSourceType(),
            'source_id' => $item->getSourceId(),
            'assigned_user_id' => $item->getAssignedUserId(),
            'department_id' => $item->getDepartmentId(),
            'priority_score' => $item->getPriorityScore(),
            'status' => $item->getStatus(),
            'sla_time_remaining' => $item->getSlaTimeRemainingMinutes(),
            'due_date' => $item->getScheduledDueUtc()?->format('Y-m-d H:i:s'),
            'manager_override' => $item->getManagerPriorityOverride(),
            'revenue' => $item->getRevenue(),
            'client_tier' => $item->getClientTier(),
            'signals' => array_map(function($signal) {
                return [
                    'type' => $signal->getSignalType(),
                    'severity' => $signal->getSeverity(),
                    'message' => $signal->getMessage(),
                    'created_at' => $signal->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $signals),
        ];
    }

    public function checkPermission(): bool
    {
        return current_user_can('edit_posts');
    }
}
