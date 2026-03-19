<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class AdvisorySignalController implements RestController
{
    private const NAMESPACE = 'pet/v1';

    public function __construct(
        private FeatureFlagService $featureFlags,
        private AdvisorySignalRepository $signals
    ) {
    }

    public function registerRoutes(): void
    {
        if (!$this->featureFlags->isAdvisoryEnabled()) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/advisory/signals/recent', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'recentSignals'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'limit' => ['required' => false, 'type' => 'integer'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/advisory/signals/work-item/(?P<id>[a-zA-Z0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'signalsForWorkItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return is_user_logged_in() && current_user_can('edit_posts');
    }

    public function recentSignals(WP_REST_Request $request): WP_REST_Response
    {
        $limit = (int)($request->get_param('limit') ?? 20);
        if ($limit <= 0) {
            $limit = 20;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $signals = $this->signals->findRecent($limit);
        $data = array_map(fn($s) => $this->serializeSignal($s), $signals);
        return new WP_REST_Response($data, 200);
    }

    public function signalsForWorkItem(WP_REST_Request $request): WP_REST_Response
    {
        $id = (string)$request->get_param('id');
        $signals = $this->signals->findByWorkItemId($id);
        $data = array_map(fn($s) => $this->serializeSignal($s), $signals);
        return new WP_REST_Response($data, 200);
    }

    private function serializeSignal($signal): array
    {
        return [
            'id' => $signal->getId(),
            'work_item_id' => $signal->getWorkItemId(),
            'signal_type' => $signal->getSignalType(),
            'severity' => $signal->getSeverity(),
            'status' => $signal->getStatus(),
            'resolved_at' => $signal->getResolvedAt()?->format('Y-m-d H:i:s'),
            'generation_run_id' => $signal->getGenerationRunId(),
            'title' => $signal->getTitle(),
            'summary' => $signal->getSummary(),
            'metadata' => $signal->getMetadata(),
            'source_entity_type' => $signal->getSourceEntityType(),
            'source_entity_id' => $signal->getSourceEntityId(),
            'customer_id' => $signal->getCustomerId(),
            'site_id' => $signal->getSiteId(),
            'message' => $signal->getMessage(),
            'created_at' => $signal->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}

