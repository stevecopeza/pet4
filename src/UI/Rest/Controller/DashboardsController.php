<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Dashboard\Service\DashboardCompositionService;
use Pet\Application\System\Service\FeatureFlagService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class DashboardsController implements RestController
{
    private const NAMESPACE = 'pet/v1';

    public function __construct(
        private FeatureFlagService $featureFlags,
        private DashboardCompositionService $composition
    ) {
    }

    public function registerRoutes(): void
    {
        if (!$this->featureFlags->isDashboardsEnabled()) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/dashboards/me/summary', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'meSummary'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'team_id' => ['required' => false, 'type' => 'integer'],
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return is_user_logged_in() && current_user_can('edit_posts');
    }

    public function meSummary(WP_REST_Request $request): WP_REST_Response
    {
        $teamId = $request->get_param('team_id');
        $teamId = $teamId !== null ? (int)$teamId : null;

        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');

        $data = $this->composition->getMeSummary($wpUserId, $isAdmin, $teamId);

        if ($teamId !== null && $teamId > 0 && $data['active_scope'] === null) {
            return new WP_REST_Response(['message' => 'Forbidden'], 403);
        }

        return new WP_REST_Response($data, 200);
    }
}

