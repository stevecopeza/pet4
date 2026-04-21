<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Resilience\Command\GenerateResilienceAnalysisCommand;
use Pet\Application\Resilience\Command\GenerateResilienceAnalysisHandler;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Dashboard\Service\DashboardAccessPolicy;
use Pet\Domain\Resilience\Repository\ResilienceAnalysisRunRepository;
use Pet\Domain\Resilience\Repository\ResilienceSignalRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ResilienceController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'resilience';

    public function __construct(
        private FeatureFlagService $featureFlags,
        private DashboardAccessPolicy $accessPolicy,
        private ResilienceAnalysisRunRepository $runs,
        private ResilienceSignalRepository $signals,
        private GenerateResilienceAnalysisHandler $generateHandler
    ) {
    }

    public function registerRoutes(): void
    {
        if (!$this->featureFlags->isResilienceIndicatorsEnabled()) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/runs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listRuns'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'team_id' => ['required' => true, 'type' => 'integer'],
                    'limit' => ['required' => false, 'type' => 'integer'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/signals', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listSignals'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'team_id' => ['required' => true, 'type' => 'integer'],
                    'run_id' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/summary/latest', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'latestSummary'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'team_id' => ['required' => true, 'type' => 'integer'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/generate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'team_id' => ['required' => true, 'type' => 'integer'],
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return \Pet\UI\Rest\Support\PortalPermissionHelper::check('pet_sales', 'pet_hr', 'pet_manager');
    }

    public function listRuns(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->featureFlags->isResilienceIndicatorsEnabled()) {
            return new WP_REST_Response(['message' => 'Feature disabled'], 403);
        }

        $teamId = (int)$request->get_param('team_id');
        $scope = $this->requireTeamScope($teamId);
        if (!$scope) {
            return new WP_REST_Response(['message' => 'Forbidden'], 403);
        }

        $limit = $request->get_param('limit');
        $limit = $limit !== null ? max(1, min(200, (int)$limit)) : 50;

        $runs = $this->runs->findByScope('team', $teamId, $limit);
        $data = array_map(fn($r) => [
            'id' => $r->id(),
            'scope_type' => $r->scopeType(),
            'scope_id' => $r->scopeId(),
            'version_number' => $r->versionNumber(),
            'status' => $r->status(),
            'started_at' => $r->startedAt()->format('c'),
            'completed_at' => $r->completedAt() ? $r->completedAt()->format('c') : null,
            'generated_by' => $r->generatedBy(),
            'summary' => $r->summary(),
        ], $runs);

        return new WP_REST_Response($data, 200);
    }

    public function listSignals(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->featureFlags->isResilienceIndicatorsEnabled()) {
            return new WP_REST_Response(['message' => 'Feature disabled'], 403);
        }

        $teamId = (int)$request->get_param('team_id');
        $scope = $this->requireTeamScope($teamId);
        if (!$scope) {
            return new WP_REST_Response(['message' => 'Forbidden'], 403);
        }

        $runId = $request->get_param('run_id');
        if ($runId) {
            $signals = $this->signals->findByAnalysisRunId((string)$runId);
        } else {
            $signals = $this->signals->findActiveByScope('team', $teamId);
        }

        $data = array_map(fn($s) => [
            'id' => $s->id(),
            'analysis_run_id' => $s->analysisRunId(),
            'scope_type' => $s->scopeType(),
            'scope_id' => $s->scopeId(),
            'signal_type' => $s->signalType(),
            'severity' => $s->severity(),
            'title' => $s->title(),
            'summary' => $s->summary(),
            'employee_id' => $s->employeeId(),
            'team_id' => $s->teamId(),
            'role_id' => $s->roleId(),
            'source_entity_type' => $s->sourceEntityType(),
            'source_entity_id' => $s->sourceEntityId(),
            'status' => $s->status(),
            'created_at' => $s->createdAt()->format('c'),
            'resolved_at' => $s->resolvedAt() ? $s->resolvedAt()->format('c') : null,
            'metadata' => $s->metadata(),
        ], $signals);

        return new WP_REST_Response($data, 200);
    }

    public function latestSummary(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->featureFlags->isResilienceIndicatorsEnabled()) {
            return new WP_REST_Response(['message' => 'Feature disabled'], 403);
        }

        $teamId = (int)$request->get_param('team_id');
        $scope = $this->requireTeamScope($teamId);
        if (!$scope) {
            return new WP_REST_Response(['message' => 'Forbidden'], 403);
        }

        $run = $this->runs->findLatestByScope('team', $teamId);
        if (!$run) {
            return new WP_REST_Response(['message' => 'Not found'], 404);
        }

        $signals = $this->signals->findByAnalysisRunId($run->id());
        $data = [
            'run' => [
                'id' => $run->id(),
                'scope_type' => $run->scopeType(),
                'scope_id' => $run->scopeId(),
                'version_number' => $run->versionNumber(),
                'status' => $run->status(),
                'started_at' => $run->startedAt()->format('c'),
                'completed_at' => $run->completedAt() ? $run->completedAt()->format('c') : null,
                'generated_by' => $run->generatedBy(),
                'summary' => $run->summary(),
            ],
            'signals' => array_map(fn($s) => [
                'id' => $s->id(),
                'signal_type' => $s->signalType(),
                'severity' => $s->severity(),
                'title' => $s->title(),
                'summary' => $s->summary(),
                'employee_id' => $s->employeeId(),
                'status' => $s->status(),
                'created_at' => $s->createdAt()->format('c'),
                'metadata' => $s->metadata(),
            ], $signals),
        ];

        return new WP_REST_Response($data, 200);
    }

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->featureFlags->isResilienceIndicatorsEnabled()) {
            return new WP_REST_Response(['message' => 'Feature disabled'], 403);
        }

        $teamId = (int)$request->get_param('team_id');
        $scope = $this->requireTeamScope($teamId);
        if (!$scope) {
            return new WP_REST_Response(['message' => 'Forbidden'], 403);
        }

        $isAdmin = current_user_can('manage_options');
        $visibility = (string)($scope['visibility_scope'] ?? '');
        if (!$isAdmin && $visibility === 'TEAM') {
            return new WP_REST_Response(['message' => 'Forbidden'], 403);
        }
        try {
            $runId = $this->generateHandler->handle(new GenerateResilienceAnalysisCommand($teamId, (int)get_current_user_id()));
            return new WP_REST_Response(['analysis_run_id' => $runId], 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'Failed to generate resilience analysis'], 500);
        }
        return new WP_REST_Response(['analysis_run_id' => $runId], 201);
    }

    private function requireTeamScope(int $teamId): ?array
    {
        if ($teamId <= 0) {
            return null;
        }

        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');
        return $this->accessPolicy->resolveTeamScope($wpUserId, $isAdmin, $teamId);
    }
}

