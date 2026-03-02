<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
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
        $this->activityLogRepository = $activityLogRepository;
        $this->timeEntryRepository = $timeEntryRepository;
        $this->personSkillRepository = $personSkillRepository;
        $this->personKpiRepository = $personKpiRepository;
        $this->escalationRuleRepository = $escalationRuleRepository;
        $this->slaClockStateRepository = $slaClockStateRepository;
        $this->ticketRepository = $ticketRepository;
        $this->featureFlagService = $featureFlagService;
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

        $data = [
            'overview' => [
                'activeProjects' => $activeProjects,
                'pendingQuotes' => $pendingQuotes,
                'utilizationRate' => $utilizationRate,
                'revenueThisMonth' => $revenueThisMonth,
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
                    'escalationRulesUrl' => admin_url('admin.php?page=pet-escalation-rules'),
                    'helpdeskUrl' => admin_url('admin.php?page=pet-helpdesk'),
                ],
            ];
        }

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
