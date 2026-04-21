<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\System\Service\FeatureFlagService;
use Pet\Application\Work\Service\WorkQueueQueryService;
use Pet\Application\Work\Service\WorkQueueVisibilityService;
use Pet\Domain\Team\Repository\TeamRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class WorkQueueController implements RestController
{
    private const NAMESPACE = 'pet/v1';

    public function __construct(
        private FeatureFlagService $featureFlags,
        private WorkQueueVisibilityService $visibility,
        private WorkQueueQueryService $query,
        private TeamRepository $teamRepository
    ) {
    }

    public function registerRoutes(): void
    {
        if (!$this->featureFlags->isWorkProjectionEnabled() || !$this->featureFlags->isQueueVisibilityEnabled()) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/work/queues', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listQueues'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/work/queues/summary', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'queueSummary'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/work/queues/(?P<queue_key>.+)/items', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'queueItems'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return \Pet\UI\Rest\Support\PortalPermissionHelper::check('pet_sales', 'pet_hr', 'pet_manager');
    }

    public function listQueues(WP_REST_Request $request): WP_REST_Response
    {
        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');

        $visible = $this->visibility->listVisibleQueues($wpUserId, $isAdmin);
        $teams = $this->indexTeamsById();

        $out = array_map(function (array $q) use ($teams) {
            $key = (string)$q['queue_key'];
            $label = $this->labelForQueueKey($key, $teams);
            return [
                'queue_key' => $key,
                'label' => $label,
                'visibility_scope' => (string)$q['visibility_scope'],
            ];
        }, $visible);

        return new WP_REST_Response($out, 200);
    }

    public function queueSummary(WP_REST_Request $request): WP_REST_Response
    {
        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');

        $visible = $this->visibility->listVisibleQueues($wpUserId, $isAdmin);
        $queueKeys = array_map(fn($q) => (string)$q['queue_key'], $visible);

        $counts = $this->query->countByQueueKeys($queueKeys);

        $out = [];
        foreach ($queueKeys as $k) {
            $out[] = ['queue_key' => $k, 'count' => $counts[$k] ?? 0];
        }

        return new WP_REST_Response($out, 200);
    }

    public function queueItems(WP_REST_Request $request): WP_REST_Response
    {
        $queueKey = urldecode((string)$request->get_param('queue_key'));
        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');

        $visible = $this->visibility->listVisibleQueues($wpUserId, $isAdmin);
        $scope = null;
        foreach ($visible as $q) {
            if ((string)$q['queue_key'] === $queueKey) {
                $scope = (string)$q['visibility_scope'];
                break;
            }
        }

        if ($scope === null) {
            return new WP_REST_Response(['message' => 'Queue not visible'], 403);
        }

        $items = $this->query->listItemsForQueue($queueKey);
        $items = array_map(function (array $it) use ($scope) {
            $it['visibility_scope'] = $scope;
            return $it;
        }, $items);

        return new WP_REST_Response($items, 200);
    }

    private function indexTeamsById(): array
    {
        $teams = [];
        foreach ($this->teamRepository->findAll(true) as $t) {
            if ($t->id() === null) {
                continue;
            }
            $teams[(string)$t->id()] = $t->name();
        }
        return $teams;
    }

    private function labelForQueueKey(string $queueKey, array $teamsById): string
    {
        $parts = explode(':', $queueKey);
        if (count($parts) < 2) {
            return $queueKey;
        }

        $domain = $parts[0];
        $kind = $parts[1];

        if ($kind === 'unrouted') {
            return ucfirst($domain) . ' — Unrouted';
        }

        if ($kind === 'user' && isset($parts[2])) {
            return ucfirst($domain) . ' — My Queue';
        }

        if ($kind === 'team' && isset($parts[2])) {
            $teamId = $parts[2];
            $name = $teamsById[$teamId] ?? ('Team ' . $teamId);
            return ucfirst($domain) . ' — ' . $name;
        }

        return $queueKey;
    }
}

