<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\LeadRepository;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Work\Repository\PersonSkillRepository;
use Pet\Domain\Work\Repository\PersonKpiRepository;
use Pet\Domain\Sla\Repository\EscalationRuleRepository;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Application\System\Service\FeatureFlagService;
use WP_REST_Request;
use WP_REST_Response;

class DashboardController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'dashboard';

    private $projectRepository;
    private $quoteRepository;
    private $leadRepository;
    private $activityLogRepository;
    private $timeEntryRepository;
    private $personSkillRepository;
    private $personKpiRepository;
    private $escalationRuleRepository;
    private $slaClockStateRepository;
    private $ticketRepository;
    private $featureFlagService;

    public function __construct(
        ProjectRepository $projectRepository,
        QuoteRepository $quoteRepository,
        LeadRepository $leadRepository,
        ActivityLogRepository $activityLogRepository,
        TimeEntryRepository $timeEntryRepository,
        PersonSkillRepository $personSkillRepository,
        PersonKpiRepository $personKpiRepository,
        EscalationRuleRepository $escalationRuleRepository,
        SlaClockStateRepository $slaClockStateRepository,
        TicketRepository $ticketRepository,
        FeatureFlagService $featureFlagService
    ) {
        $this->projectRepository = $projectRepository;
        $this->quoteRepository = $quoteRepository;
        $this->leadRepository = $leadRepository;
        $this->activityLogRepository = $activityLogRepository;
        $this->timeEntryRepository = $timeEntryRepository;
        $this->personSkillRepository = $personSkillRepository;
        $this->personKpiRepository = $personKpiRepository;
        $this->escalationRuleRepository = $escalationRuleRepository;
        $this->slaClockStateRepository = $slaClockStateRepository;
        $this->ticketRepository = $ticketRepository;
        $this->featureFlagService = $featureFlagService;
    }

    /**
     * @return array{run_id:int, workload_key:string, query_count_start:int, started_at:float}|null
     */
    private function beginBenchmarkWorkloadProfile(string $workloadKey): ?array
    {
        $activeRunId = $this->activeBenchmarkRunId();
        if ($activeRunId === null) {
            return null;
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return null;
        }

        return [
            'run_id' => $activeRunId,
            'workload_key' => $workloadKey,
            'query_count_start' => $this->queryCount($wpdb),
            'started_at' => microtime(true),
        ];
    }

    /**
     * @param array{run_id:int, workload_key:string, query_count_start:int, started_at:float}|null $token
     */
    private function endBenchmarkWorkloadProfile(?array $token): void
    {
        if ($token === null) {
            return;
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return;
        }

        $queryDelta = $this->queryCount($wpdb) - (int) $token['query_count_start'];
        if ($queryDelta < 0) {
            $queryDelta = 0;
        }

        $payload = [
            'workload_key' => (string) $token['workload_key'],
            'query_count' => $queryDelta,
            'execution_time_ms' => round((microtime(true) - (float) $token['started_at']) * 1000, 3),
        ];

        $metricsKey = 'pet_performance_workload_metrics_' . (int) $token['run_id'];
        $existing = get_transient($metricsKey);
        $rows = is_array($existing) ? $existing : [];
        $rows[] = $payload;
        set_transient($metricsKey, $rows, 10 * MINUTE_IN_SECONDS);
    }

    private function activeBenchmarkRunId(): ?int
    {
        $value = get_transient('pet_performance_active_run_id');
        if ($value === false || $value === null || !is_numeric($value)) {
            return null;
        }

        $runId = (int) $value;
        return $runId > 0 ? $runId : null;
    }

    private function queryCount(\wpdb $wpdb): int
    {
        if (property_exists($wpdb, 'num_queries') && is_numeric($wpdb->num_queries)) {
            return (int) $wpdb->num_queries;
        }
        if (defined('SAVEQUERIES') && SAVEQUERIES && property_exists($wpdb, 'queries') && is_array($wpdb->queries)) {
            return count($wpdb->queries);
        }
        return 0;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            'methods' => 'GET',
            'callback' => [$this, 'getDashboardData'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getDashboardData(WP_REST_Request $request): WP_REST_Response
    {
        $profileToken = $this->beginBenchmarkWorkloadProfile('dashboard');
        $activeProjects = $this->projectRepository->countActive();
        $pendingQuotes = $this->quoteRepository->countPending();
        $activities = $this->activityLogRepository->findAll(5); // Get last 5 activities

        // Calculate Revenue This Month
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $endOfMonth = new \DateTimeImmutable('last day of this month 23:59:59');
        $revenueThisMonth = $this->quoteRepository->sumRevenue($startOfMonth, $endOfMonth);

        // Calculate Utilization Rate (Budget Burn)
        $totalSoldHours = $this->projectRepository->sumSoldHours();
        $totalBillableHours = $this->timeEntryRepository->sumBillableHours();
        $utilizationRate = $totalSoldHours > 0 ? round(($totalBillableHours / $totalSoldHours) * 100) : 0;

        $recentActivity = array_map(function ($log) {
            return [
                'id' => $log->id(),
                'type' => $log->type(),
                'message' => $log->description(),
                'time' => $this->timeElapsedString($log->createdAt()),
            ];
        }, $activities);

        // New Metrics
        $skillHeatmap = $this->personSkillRepository->getAverageProficiencyBySkill();
        $kpiPerformance = $this->personKpiRepository->getAverageAchievementByKpi();

        // Sales metrics
        $quotesByState = $this->quoteRepository->countByState();
        $pipelineValue = $this->quoteRepository->sumValueByStates(['draft', 'sent']);
        $activeLeads = $this->leadRepository->countActive();
        $avgDealSize = $this->quoteRepository->avgAcceptedValue();
        $quotesSent = $quotesByState['sent'] ?? 0;
        $quotesAccepted = $quotesByState['accepted'] ?? 0;
        $totalDecided = $quotesAccepted + ($quotesByState['rejected'] ?? 0);
        $winRate = $totalDecided > 0 ? round(($quotesAccepted / $totalDecided) * 100) : 0;

        $data = [
            'overview' => [
                'activeProjects' => $activeProjects,
                'pendingQuotes' => $pendingQuotes,
                'utilizationRate' => $utilizationRate,
                'revenueThisMonth' => $revenueThisMonth,
            ],
            'sales' => [
                'pipelineValue' => $pipelineValue,
                'quotesSent' => $quotesSent,
                'winRate' => $winRate,
                'revenueMtd' => $revenueThisMonth,
                'activeLeads' => $activeLeads,
                'avgDealSize' => round($avgDealSize, 2),
                'quotesByState' => $quotesByState,
            ],
            'recentActivity' => $recentActivity,
            'skillHeatmap' => $skillHeatmap,
            'kpiPerformance' => $kpiPerformance,
        ];

        // Demo Wow Panel Data
        if ($this->featureFlagService->isEscalationEngineEnabled() || $this->featureFlagService->isHelpdeskEnabled()) {
            $escalationStats = $this->escalationRuleRepository->getDashboardStats();
            $slaStats = $this->slaClockStateRepository->getDashboardStats();
            
            $unassignedCount = $this->ticketRepository->countActiveUnassigned();

            $data['demoWow'] = [
                'escalationRules' => [
                    'enabledCount' => $escalationStats['enabledCount'],
                    'totalCount' => $escalationStats['totalCount'],
                ],
                'slaRisk' => [
                    'warningCount' => $slaStats['warningCount'],
                    'breachedCount' => $slaStats['breachedCount'],
                ],
                'workload' => [
                    'unassignedTicketsCount' => $unassignedCount,
                ],
                'actions' => [
                    'escalationRulesUrl' => admin_url('admin.php?page=' . ($this->featureFlagService->isEscalationEngineEnabled() ? 'pet-escalations' : 'pet-support')),
                    'helpdeskUrl' => admin_url('admin.php?page=pet-support'),
                    'advisoryUrl' => admin_url('admin.php?page=pet-advisory'),
                ],
            ];
        }

        $this->endBenchmarkWorkloadProfile($profileToken);
        return new WP_REST_Response($data, 200);
    }

    private function timeElapsedString(\DateTimeImmutable $datetime, $full = false) {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($datetime);

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}
