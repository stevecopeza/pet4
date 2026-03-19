<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Support\Query\SupportTeamOperationalSummaryQuery;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Application\Work\Service\WorkQueueQueryService;
use Pet\Application\Work\Service\WorkQueueVisibilityService;
use Pet\Domain\Dashboard\Service\DashboardAccessPolicy;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class SupportOperationalController implements RestController
{
    private const NAMESPACE = 'pet/v1';

    public function __construct(
        private FeatureFlagService $featureFlags,
        private WorkQueueVisibilityService $visibility,
        private WorkQueueQueryService $queueQuery,
        private DashboardAccessPolicy $accessPolicy,
        private SupportTeamOperationalSummaryQuery $summaryQuery,
        private EmployeeRepository $employeeRepository,
        private TeamRepository $teamRepository
    ) {
    }

    public function registerRoutes(): void
    {
        if (!$this->featureFlags->isHelpdeskEnabled() || !$this->featureFlags->isSupportOperationalImprovementsEnabled()) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/support/queue', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'queue'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/support/summary/team', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'teamSummary'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'team_id' => ['required' => true, 'type' => 'integer'],
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return is_user_logged_in();
    }

    public function queue(WP_REST_Request $request): WP_REST_Response
    {
        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');

        $visible = $this->visibility->listVisibleQueues($wpUserId, $isAdmin);
        $visible = array_values(array_filter($visible, fn($q) => str_starts_with((string)$q['queue_key'], 'support:')));

        $queueKeys = array_map(fn($q) => (string)$q['queue_key'], $visible);
        $counts = $this->queueQuery->countByQueueKeys($queueKeys);

        $teamsById = $this->indexTeamsById();
        $out = [];
        foreach ($visible as $q) {
            $k = (string)$q['queue_key'];
            $out[] = [
                'queue_key' => $k,
                'label' => $this->labelForQueueKey($k, $teamsById),
                'visibility_scope' => (string)$q['visibility_scope'],
                'count' => $counts[$k] ?? 0,
            ];
        }

        return new WP_REST_Response(['queues' => $out], 200);
    }

    public function teamSummary(WP_REST_Request $request): WP_REST_Response
    {
        $teamId = (int)$request->get_param('team_id');
        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');

        $scope = $this->accessPolicy->resolveTeamScope($wpUserId, $isAdmin, $teamId);
        if (!$scope) {
            return new WP_REST_Response(['message' => 'Forbidden'], 403);
        }

        $vis = (string)($scope['visibility_scope'] ?? '');
        if (!$isAdmin && $vis === 'TEAM') {
            return new WP_REST_Response(['message' => 'Forbidden'], 403);
        }

        $teamWpUserIds = $this->activeTeamWpUserIds($teamId);
        $summary = $this->summaryQuery->getTeamSummary($teamId, $teamWpUserIds);
        $summary['team_label'] = (string)($scope['label'] ?? ('Team ' . $teamId));
        $summary['visibility_scope'] = $vis;

        return new WP_REST_Response($summary, 200);
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

        if ($kind === 'user') {
            return ucfirst($domain) . ' — My Queue';
        }

        if ($kind === 'team' && isset($parts[2])) {
            $teamId = $parts[2];
            $name = $teamsById[$teamId] ?? ('Team ' . $teamId);
            return ucfirst($domain) . ' — ' . $name;
        }

        return $queueKey;
    }

    private function activeTeamWpUserIds(int $teamId): array
    {
        $out = [];
        foreach ($this->employeeRepository->findAll() as $e) {
            if ($e->status() !== 'active') {
                continue;
            }
            $teamIds = array_map('intval', $e->teamIds());
            if (in_array($teamId, $teamIds, true)) {
                $out[] = (string)$e->wpUserId();
            }
        }
        return array_values(array_unique(array_filter($out, fn($v) => $v !== '' && $v !== '0')));
    }
}

