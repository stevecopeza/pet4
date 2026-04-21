<?php

declare(strict_types=1);

namespace Pet\Application\Dashboard\Service;

use DateTimeImmutable;
use Pet\Application\Dashboard\Query\TeamAdvisorySummaryQuery;
use Pet\Application\Dashboard\Query\TeamEscalationSummaryQuery;
use Pet\Application\Dashboard\Query\TeamSupportSummaryQuery;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Application\Work\Service\WorkQueueQueryService;
use Pet\Application\Work\Service\WorkQueueVisibilityService;
use Pet\Domain\Dashboard\Service\DashboardAccessPolicy;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Domain\Resilience\Repository\ResilienceAnalysisRunRepository;
use Pet\Domain\Resilience\Repository\ResilienceSignalRepository;

class DashboardCompositionService
{
    public function __construct(
        private FeatureFlagService $featureFlags,
        private DashboardAccessPolicy $accessPolicy,
        private WorkQueueVisibilityService $queueVisibility,
        private WorkQueueQueryService $queueQuery,
        private TeamSupportSummaryQuery $supportSummary,
        private TeamEscalationSummaryQuery $escalationSummary,
        private TeamAdvisorySummaryQuery $advisorySummary,
        private FeedEventRepository $feedRepo,
        private ResilienceAnalysisRunRepository $resilienceRuns,
        private ResilienceSignalRepository $resilienceSignals
    ) {
    }

    public function getMeSummary(int $wpUserId, bool $isAdmin, ?int $teamId = null): array
    {
        $asOf = (new DateTimeImmutable())->format('c');

        $scopes = $this->accessPolicy->listVisibleTeamScopes($wpUserId, $isAdmin);
        $active = null;

        if ($teamId !== null) {
            $active = $this->accessPolicy->resolveTeamScope($wpUserId, $isAdmin, $teamId);
        }
        if ($active === null && !empty($scopes)) {
            $active = $this->preferScope($scopes);
        }

        $activeScope = $active ? (string)($active['visibility_scope'] ?? null) : null;
        $allowedPersonas = $this->accessPolicy->listAllowedPersonas($activeScope, $isAdmin);

        $personas = [];
        foreach ($allowedPersonas as $persona) {
            if ($active === null) {
                $personas[$persona] = ['panels' => []];
                continue;
            }
            $personas[$persona] = ['panels' => $this->composePanelsForPersona($persona, $wpUserId, $isAdmin, (int)$active['scope_id'], (string)$active['visibility_scope'], $asOf)];
        }

        return [
            'as_of' => $asOf,
            'scopes' => $scopes,
            'active_scope' => $active,
            'allowed_personas' => $allowedPersonas,
            'personas' => $personas,
        ];
    }

    private function preferScope(array $scopes): array
    {
        $rank = ['TEAM' => 2, 'MANAGERIAL' => 3, 'ADMIN' => 4];
        usort($scopes, function ($a, $b) use ($rank) {
            $ra = $rank[(string)($a['visibility_scope'] ?? '')] ?? 0;
            $rb = $rank[(string)($b['visibility_scope'] ?? '')] ?? 0;
            if ($ra !== $rb) {
                return $rb <=> $ra;
            }
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });
        return $scopes[0];
    }

    private function composePanelsForPersona(string $persona, int $wpUserId, bool $isAdmin, int $teamId, string $visibilityScope, string $asOf): array
    {
        $panels = [];

        if ($persona === 'manager') {
            $panels = array_merge($panels, $this->managerPanels($wpUserId, $isAdmin, $teamId, $visibilityScope, $asOf));
        } elseif ($persona === 'support') {
            $panels = array_merge($panels, $this->supportPanels($wpUserId, $isAdmin, $teamId, $visibilityScope, $asOf));
        } elseif ($persona === 'pm') {
            $panels[] = $this->panel(
                'pm_stub',
                'Delivery Summary',
                null,
                null,
                'info',
                'team',
                $teamId,
                $asOf,
                null,
                [],
                'Delivery summary composition not yet mapped to team truth.'
            );
        } elseif ($persona === 'timesheets') {
            $panels[] = $this->panel(
                'timesheets_stub',
                'Time Summary',
                null,
                null,
                'info',
                'team',
                $teamId,
                $asOf,
                null,
                [],
                'Timesheet summary composition not yet mapped to team truth.'
            );
        } elseif ($persona === 'sales') {
            if ($isAdmin) {
                $panels[] = $this->panel(
                    'sales_stub',
                    'Sales Summary',
                    null,
                    null,
                    'info',
                    'team',
                    $teamId,
                    $asOf,
                    null,
                    [],
                    'Sales summary composition is admin-only in this phase.'
                );
            }
        }

        return $panels;
    }

    private function managerPanels(int $wpUserId, bool $isAdmin, int $teamId, string $visibilityScope, string $asOf): array
    {
        $panels = [];

        $queues = $this->managerQueuePanel($wpUserId, $isAdmin, $teamId, $visibilityScope, $asOf);
        if ($queues !== null) {
            $panels[] = $queues;
        }

        $support = $this->supportSummary->getSummaryForTeam($teamId);
        $panels[] = $this->panel(
            'support_workload',
            'Support Workload',
            (int)$support['open_tickets'],
            'tickets',
            $this->severityFromCounts((int)$support['breached_tickets'], (int)$support['warning_tickets']),
            'team',
            $teamId,
            $asOf,
            [
                'open_tickets' => (int)$support['open_tickets'],
                'breached_tickets' => (int)$support['breached_tickets'],
                'warning_tickets' => (int)$support['warning_tickets'],
                'unassigned_tickets' => (int)$support['unassigned_tickets'],
            ],
            [],
            null
        );

        if ($this->featureFlags->isEscalationEngineEnabled()) {
            $esc = $this->escalationSummary->getOpenSummaryForTeam($teamId);
            $panels[] = $this->panel(
                'escalation_summary',
                'Open Escalations',
                (int)$esc['total_open'],
                'open',
                ((int)$esc['total_open'] > 0) ? 'warning' : 'info',
                'team',
                $teamId,
                $asOf,
                [
                    'by_severity' => $esc['by_severity'],
                ],
                [],
                null
            );
        }

        if ($this->featureFlags->isAdvisoryEnabled()) {
            $adv = $this->advisorySummary->getActiveSummaryForTeam($teamId);
            $panels[] = $this->panel(
                'advisory_summary',
                'Advisory Signals',
                (int)$adv['total_active'],
                'active',
                ((int)$adv['total_active'] > 0) ? 'attention' : 'info',
                'team',
                $teamId,
                $asOf,
                [
                    'by_severity' => $adv['by_severity'],
                ],
                [],
                null
            );
        }

        $resilience = $this->resiliencePanel($teamId, $asOf);
        if ($resilience !== null) {
            $panels[] = $resilience;
        }

        $panels[] = $this->recentActivityPanel($wpUserId, $teamId, $asOf);

        return $panels;
    }

    private function supportPanels(int $wpUserId, bool $isAdmin, int $teamId, string $visibilityScope, string $asOf): array
    {
        $panels = [];

        $teamQueueKey = "support:team:{$teamId}";
        $counts = $this->queueQuery->countByQueueKeys([$teamQueueKey]);
        $items = $this->queueQuery->listItemsForQueue($teamQueueKey);
        $panels[] = $this->panel(
            'team_queue',
            'Team Queue',
            (int)($counts[$teamQueueKey] ?? 0),
            'items',
            ((int)($counts[$teamQueueKey] ?? 0) > 20) ? 'attention' : 'info',
            'team',
            $teamId,
            $asOf,
            ['queue_key' => $teamQueueKey],
            array_slice($items, 0, 10),
            null
        );

        $myQueueKey = "support:user:{$wpUserId}";
        $myCount = $this->queueQuery->countByQueueKeys([$myQueueKey]);
        $myItems = $this->queueQuery->listItemsForQueue($myQueueKey);
        $panels[] = $this->panel(
            'my_queue',
            'My Queue',
            (int)($myCount[$myQueueKey] ?? 0),
            'items',
            ((int)($myCount[$myQueueKey] ?? 0) > 10) ? 'attention' : 'info',
            'team',
            $teamId,
            $asOf,
            ['queue_key' => $myQueueKey],
            array_slice($myItems, 0, 10),
            null
        );

        if ($this->featureFlags->isAdvisoryEnabled()) {
            $adv = $this->advisorySummary->getActiveSummaryForTeam($teamId);
            $panels[] = $this->panel(
                'advisory_signals',
                'Signals',
                (int)$adv['total_active'],
                'active',
                ((int)$adv['total_active'] > 0) ? 'attention' : 'info',
                'team',
                $teamId,
                $asOf,
                [
                    'by_severity' => $adv['by_severity'],
                ],
                [],
                null
            );
        }

        return $panels;
    }

    private function managerQueuePanel(int $wpUserId, bool $isAdmin, int $teamId, string $visibilityScope, string $asOf): ?array
    {
        $visibleQueues = $this->queueVisibility->listVisibleQueues($wpUserId, $isAdmin);
        $queueKeys = [];
        foreach ($visibleQueues as $q) {
            $key = (string)$q['queue_key'];
            if ($key === "support:team:{$teamId}" || $key === "delivery:team:{$teamId}") {
                $queueKeys[] = $key;
            }
            if ($key === 'support:unrouted' && $visibilityScope !== 'TEAM') {
                $queueKeys[] = $key;
            }
            if ($key === 'delivery:unrouted' && $visibilityScope !== 'TEAM') {
                $queueKeys[] = $key;
            }
        }
        $queueKeys = array_values(array_unique($queueKeys));
        if (empty($queueKeys)) {
            return null;
        }

        $counts = $this->queueQuery->countByQueueKeys($queueKeys);
        $total = array_sum(array_values($counts));

        return $this->panel(
            'queue_summary',
            'Queue Summary',
            $total,
            'items',
            ($total > 30) ? 'attention' : 'info',
            'team',
            $teamId,
            $asOf,
            [
                'by_queue_key' => $counts,
            ],
            [],
            null
        );
    }

    private function recentActivityPanel(int $wpUserId, int $teamId, string $asOf): array
    {
        $events = $this->feedRepo->findRelevantForUser((string)$wpUserId, [(string)$teamId], [], 15);
        $items = [];
        foreach ($events as $e) {
            $items[] = [
                'id' => $e->getId(),
                'event_type' => $e->getEventType(),
                'classification' => $e->getClassification(),
                'title' => $e->getTitle(),
                'summary' => $e->getSummary(),
                'created_at' => $e->getCreatedAt()->format('c'),
                'metadata' => $e->getMetadata(),
            ];
        }

        return $this->panel(
            'recent_activity',
            'Recent Activity',
            count($items),
            'events',
            'info',
            'team',
            $teamId,
            $asOf,
            null,
            $items,
            null
        );
    }

    private function panel(
        string $panelKey,
        string $title,
        $metricValue,
        ?string $metricUnit,
        ?string $severity,
        string $scopeType,
        int $scopeId,
        string $asOf,
        ?array $countBreakdown,
        array $items,
        ?string $sourceSummary,
        ?array $actions = null
    ): array {
        return [
            'panel_key' => $panelKey,
            'title' => $title,
            'metric_value' => $metricValue,
            'metric_unit' => $metricUnit,
            'severity' => $severity,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'as_of' => $asOf,
            'count_breakdown' => $countBreakdown,
            'items' => $items,
            'source_summary' => $sourceSummary,
            'actions' => $actions,
        ];
    }

    private function severityFromCounts(int $breached, int $warning): string
    {
        if ($breached > 0) {
            return 'critical';
        }
        if ($warning > 0) {
            return 'warning';
        }
        return 'info';
    }

    private function resiliencePanel(int $teamId, string $asOf): ?array
    {
        if (!$this->featureFlags->isResilienceIndicatorsEnabled()) {
            return null;
        }

        $run = $this->resilienceRuns->findLatestByScope('team', $teamId);
        $signals = $this->resilienceSignals->findActiveByScope('team', $teamId, 50);

        $severityRank = ['info' => 1, 'warning' => 2, 'critical' => 3];
        $maxSev = 'info';
        foreach ($signals as $s) {
            $sev = $s->severity();
            if (($severityRank[$sev] ?? 0) > ($severityRank[$maxSev] ?? 0)) {
                $maxSev = $sev;
            }
        }

        $items = array_map(fn($s) => [
            'id' => $s->id(),
            'signal_type' => $s->signalType(),
            'severity' => $s->severity(),
            'title' => $s->title(),
            'summary' => $s->summary(),
            'employee_id' => $s->employeeId(),
            'created_at' => $s->createdAt()->format('c'),
            'analysis_run_id' => $s->analysisRunId(),
        ], $signals);

        $actions = [
            [
                'label' => 'Generate',
                'method' => 'POST',
                'path' => 'resilience/generate',
                'body' => ['team_id' => $teamId],
            ],
        ];

        if (!$run) {
            return $this->panel(
                'resilience_summary',
                'Resilience',
                null,
                null,
                'info',
                'team',
                $teamId,
                $asOf,
                null,
                [],
                'No resilience analysis exists for this team.',
                $actions
            );
        }

        $summary = $run->summary();
        return $this->panel(
            'resilience_summary',
            'Resilience',
            count($signals),
            'signals',
            $maxSev,
            'team',
            $teamId,
            $asOf,
            $summary,
            $items,
            'Latest run: v' . $run->versionNumber(),
            $actions
        );
    }
}
