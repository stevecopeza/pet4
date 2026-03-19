<?php

declare(strict_types=1);

namespace Pet\Application\Resilience\Service;

use DateInterval;
use DateTimeImmutable;
use Pet\Application\Resilience\Query\TeamWorkloadConcentrationQuery;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Resilience\Entity\ResilienceAnalysisRun;
use Pet\Domain\Resilience\Entity\ResilienceSignal;
use Pet\Domain\Resilience\Repository\ResilienceAnalysisRunRepository;
use Pet\Domain\Resilience\Repository\ResilienceSignalRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Domain\Work\Service\CapacityCalendar;

class ResilienceAnalysisGenerator
{
    public function __construct(
        private ResilienceAnalysisRunRepository $runs,
        private ResilienceSignalRepository $signals,
        private EmployeeRepository $employees,
        private TeamRepository $teams,
        private CapacityCalendar $capacityCalendar,
        private TeamWorkloadConcentrationQuery $workloadQuery
    ) {
    }

    public function generateForTeam(int $teamId, ?int $generatedByWpUserId = null): string
    {
        $team = $this->teams->find($teamId);
        if (!$team) {
            throw new \RuntimeException('Team not found');
        }

        $now = new DateTimeImmutable('now');
        $scopeType = 'team';
        $version = $this->runs->findNextVersionNumber($scopeType, $teamId);

        $runId = $this->uuid();
        $this->runs->save(new ResilienceAnalysisRun(
            $runId,
            $scopeType,
            $teamId,
            $version,
            'RUNNING',
            $now,
            null,
            $generatedByWpUserId,
            null
        ));

        $this->signals->deactivateActiveForScope($scopeType, $teamId, gmdate('Y-m-d H:i:s'));

        $members = $this->activeTeamMembers($teamId);

        $emitted = [];
        foreach ($this->utilizationOverloadSignals($runId, $teamId, $members, $now) as $s) {
            $emitted[] = $s;
        }
        foreach ($this->teamSpofSignals($runId, $teamId, $members, $now) as $s) {
            $emitted[] = $s;
        }
        foreach ($this->workloadConcentrationSignals($runId, $teamId, $members, $now) as $s) {
            $emitted[] = $s;
        }

        foreach ($emitted as $signal) {
            $this->signals->save($signal);
        }

        $summary = $this->buildSummary($emitted, $team->name());
        $this->runs->save(new ResilienceAnalysisRun(
            $runId,
            $scopeType,
            $teamId,
            $version,
            'COMPLETED',
            $now,
            $now,
            $generatedByWpUserId,
            $summary
        ));

        return $runId;
    }

    private function activeTeamMembers(int $teamId): array
    {
        $out = [];
        foreach ($this->employees->findAll() as $e) {
            if ($e->status() !== 'active') {
                continue;
            }
            $teamIds = array_map('intval', $e->teamIds());
            if (in_array($teamId, $teamIds, true)) {
                $out[] = $e;
            }
        }
        return $out;
    }

    private function utilizationOverloadSignals(string $runId, int $teamId, array $members, DateTimeImmutable $now): array
    {
        $signals = [];

        $start = $now->sub(new DateInterval('P6D'));
        $start = new DateTimeImmutable($start->format('Y-m-d') . ' 00:00:00');
        $end = new DateTimeImmutable($now->format('Y-m-d') . ' 23:59:59');

        foreach ($members as $e) {
            if ($e->id() === null) {
                continue;
            }
            $rows = $this->capacityCalendar->getUserDailyUtilization($e->id(), $start, $end);
            $max = 0.0;
            $maxDate = null;
            foreach ($rows as $r) {
                $u = isset($r['utilization_pct']) ? (float)$r['utilization_pct'] : 0.0;
                if ($u > $max) {
                    $max = $u;
                    $maxDate = $r['date'] ?? null;
                }
            }

            $severity = null;
            if ($max >= 125.0) {
                $severity = ResilienceSignal::SEVERITY_CRITICAL;
            } elseif ($max >= 100.0) {
                $severity = ResilienceSignal::SEVERITY_WARNING;
            }

            if ($severity === null) {
                continue;
            }

            $signals[] = new ResilienceSignal(
                $this->uuid(),
                $runId,
                'team',
                $teamId,
                ResilienceSignal::TYPE_UTILIZATION_OVERLOAD,
                $severity,
                'Utilization overload: ' . $e->fullName(),
                sprintf('%s reached %.1f%% utilization on %s.', $e->fullName(), $max, $maxDate ?: $now->format('Y-m-d')),
                $now,
                $e->id(),
                $teamId,
                null,
                'employee',
                (string)$e->id(),
                'ACTIVE',
                null,
                ['max_utilization_pct' => round($max, 2), 'max_date' => $maxDate]
            );
        }

        return $signals;
    }

    private function teamSpofSignals(string $runId, int $teamId, array $members, DateTimeImmutable $now): array
    {
        if (count($members) !== 1) {
            return [];
        }

        $only = $members[0];
        $name = $only instanceof Employee ? $only->fullName() : 'Unknown';

        return [
            new ResilienceSignal(
                $this->uuid(),
                $runId,
                'team',
                $teamId,
                ResilienceSignal::TYPE_TEAM_SPOF,
                ResilienceSignal::SEVERITY_CRITICAL,
                'Team SPOF',
                'Only 1 active team member: ' . $name . '.',
                $now,
                $only instanceof Employee ? $only->id() : null,
                $teamId,
                null,
                null,
                null,
                'ACTIVE',
                null,
                ['active_member_count' => 1]
            ),
        ];
    }

    private function workloadConcentrationSignals(string $runId, int $teamId, array $members, DateTimeImmutable $now): array
    {
        $counts = $this->workloadQuery->countOpenAssignedByUserForTeam($teamId);
        if (empty($counts)) {
            return [];
        }

        $total = array_sum(array_values($counts));
        if ($total < 10) {
            return [];
        }

        arsort($counts);
        $topUserId = array_key_first($counts);
        $topCount = (int)($counts[$topUserId] ?? 0);
        if ($topCount <= 0) {
            return [];
        }

        $ratio = $total > 0 ? ($topCount / $total) : 0.0;
        $severity = null;
        if ($ratio >= 0.75) {
            $severity = ResilienceSignal::SEVERITY_CRITICAL;
        } elseif ($ratio >= 0.60) {
            $severity = ResilienceSignal::SEVERITY_WARNING;
        }
        if ($severity === null) {
            return [];
        }

        $employeeId = null;
        $employeeName = $topUserId;
        foreach ($members as $m) {
            if ((string)$m->wpUserId() === (string)$topUserId) {
                $employeeId = $m->id();
                $employeeName = $m->fullName();
                break;
            }
        }

        return [
            new ResilienceSignal(
                $this->uuid(),
                $runId,
                'team',
                $teamId,
                ResilienceSignal::TYPE_WORKLOAD_CONCENTRATION,
                $severity,
                'Workload concentration',
                sprintf('Top assignee %s holds %d/%d open items (%.0f%%).', $employeeName, $topCount, $total, $ratio * 100.0),
                $now,
                $employeeId,
                $teamId,
                null,
                'work_items',
                (string)$topUserId,
                'ACTIVE',
                null,
                ['top_assignee_wp_user_id' => $topUserId, 'top_count' => $topCount, 'total_open_assigned' => $total, 'ratio' => round($ratio, 4)]
            ),
        ];
    }

    private function buildSummary(array $signals, string $teamName): array
    {
        $byType = [];
        $bySeverity = [];
        foreach ($signals as $s) {
            $byType[$s->signalType()] = ($byType[$s->signalType()] ?? 0) + 1;
            $bySeverity[$s->severity()] = ($bySeverity[$s->severity()] ?? 0) + 1;
        }
        ksort($byType);
        ksort($bySeverity);

        return [
            'team_name' => $teamName,
            'total_signals' => count($signals),
            'by_type' => $byType,
            'by_severity' => $bySeverity,
        ];
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}

