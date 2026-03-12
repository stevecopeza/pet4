<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Escalation\Command\AcknowledgeEscalationCommand;
use Pet\Application\Escalation\Command\AcknowledgeEscalationHandler;
use Pet\Application\Escalation\Command\ResolveEscalationCommand;
use Pet\Application\Escalation\Command\ResolveEscalationHandler;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Repository\EscalationRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EscalationController
{
    private EscalationRepository $repository;
    private AcknowledgeEscalationHandler $acknowledgeHandler;
    private ResolveEscalationHandler $resolveHandler;
    private FeatureFlagService $featureFlagService;

    public function __construct(
        EscalationRepository $repository,
        AcknowledgeEscalationHandler $acknowledgeHandler,
        ResolveEscalationHandler $resolveHandler,
        FeatureFlagService $featureFlagService
    ) {
        $this->repository = $repository;
        $this->acknowledgeHandler = $acknowledgeHandler;
        $this->resolveHandler = $resolveHandler;
        $this->featureFlagService = $featureFlagService;
    }

    public function registerRoutes(): void
    {
        if (!$this->featureFlagService->isEscalationEngineEnabled()) {
            return;
        }

        register_rest_route('pet/v1', '/escalations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listEscalations'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('pet/v1', '/escalations/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getEscalation'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('pet/v1', '/escalations/(?P<id>\d+)/acknowledge', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'acknowledgeEscalation'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('pet/v1', '/escalations/(?P<id>\d+)/resolve', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'resolveEscalation'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function listEscalations(WP_REST_Request $request): WP_REST_Response
    {
        $status = $request->get_param('status');
        $page = max(1, (int)($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->get_param('per_page') ?? 20)));
        $offset = ($page - 1) * $perPage;

        if ($status === 'open') {
            $escalations = $this->repository->findOpen();
            $total = count($escalations);
            $escalations = array_slice($escalations, $offset, $perPage);
        } else {
            $escalations = $this->repository->findAll($perPage, $offset);
            $total = $this->repository->count();
        }

        $data = array_map([$this, 'serialize'], $escalations);

        return new WP_REST_Response([
            'items' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ], 200);
    }

    public function getEscalation(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $escalation = $this->repository->findById($id);

        if (!$escalation) {
            return new WP_REST_Response(['code' => 'not_found', 'message' => 'Escalation not found'], 404);
        }

        $data = $this->serialize($escalation);
        $data['transitions'] = $this->repository->findTransitionsByEscalationId($id);

        return new WP_REST_Response($data, 200);
    }

    public function acknowledgeEscalation(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int)$request->get_param('id');

        try {
            $command = new AcknowledgeEscalationCommand($id, get_current_user_id());
            $this->acknowledgeHandler->handle($command);

            $escalation = $this->repository->findById($id);
            return new WP_REST_Response($this->serialize($escalation), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['code' => 'domain_error', 'message' => $e->getMessage()], 400);
        }
    }

    public function resolveEscalation(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $params = $request->get_json_params();
        $resolutionNote = $params['resolution_note'] ?? null;

        try {
            $command = new ResolveEscalationCommand($id, get_current_user_id(), $resolutionNote);
            $this->resolveHandler->handle($command);

            $escalation = $this->repository->findById($id);
            return new WP_REST_Response($this->serialize($escalation), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['code' => 'domain_error', 'message' => $e->getMessage()], 400);
        }
    }

    private function serialize(Escalation $escalation): array
    {
        return [
            'id' => $escalation->id(),
            'escalation_id' => $escalation->escalationId(),
            'source_entity_type' => $escalation->sourceEntityType(),
            'source_entity_id' => $escalation->sourceEntityId(),
            'severity' => $escalation->severity(),
            'status' => $escalation->status(),
            'reason' => $escalation->reason(),
            'metadata' => json_decode($escalation->metadataJson(), true),
            'created_by' => $escalation->createdBy(),
            'acknowledged_by' => $escalation->acknowledgedBy(),
            'resolved_by' => $escalation->resolvedBy(),
            'created_at' => $escalation->createdAt()->format('c'),
            'acknowledged_at' => $escalation->acknowledgedAt()?->format('c'),
            'resolved_at' => $escalation->resolvedAt()?->format('c'),
        ];
    }
}
