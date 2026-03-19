<?php

declare(strict_types=1);

namespace Pet\Domain\Dashboard\Service;

use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Repository\TeamRepository;

class DashboardAccessPolicy
{
    public function __construct(
        private EmployeeRepository $employeeRepository,
        private TeamRepository $teamRepository
    ) {
    }

    public function listVisibleTeamScopes(int $wpUserId, bool $isAdmin): array
    {
        $scopes = [];

        if ($isAdmin) {
            foreach ($this->teamRepository->findAll(true) as $team) {
                if ($team->id() === null) {
                    continue;
                }
                $scopes[] = [
                    'scope_type' => 'team',
                    'scope_id' => $team->id(),
                    'visibility_scope' => 'ADMIN',
                    'label' => $team->name(),
                ];
            }
            usort($scopes, fn($a, $b) => strcmp((string)$a['label'], (string)$b['label']));
            return $scopes;
        }

        $employee = $this->employeeRepository->findByWpUserId($wpUserId);
        if (!$employee || $employee->id() === null) {
            return [];
        }

        $teamsById = [];
        foreach ($this->teamRepository->findAll(true) as $team) {
            if ($team->id() === null) {
                continue;
            }
            $teamsById[$team->id()] = $team;
        }

        $teamIds = array_values(array_unique(array_map('intval', $employee->teamIds())));
        $managed = $this->managedTeamIds($teamsById, $employee->id());

        $combined = [];
        foreach ($teamIds as $tid) {
            $combined[$tid] = $this->maxScope($combined[$tid] ?? null, 'TEAM');
        }
        foreach ($managed as $tid) {
            $combined[$tid] = $this->maxScope($combined[$tid] ?? null, 'MANAGERIAL');
        }

        foreach ($combined as $tid => $scope) {
            $label = isset($teamsById[$tid]) ? $teamsById[$tid]->name() : ('Team ' . $tid);
            $scopes[] = [
                'scope_type' => 'team',
                'scope_id' => (int)$tid,
                'visibility_scope' => $scope,
                'label' => $label,
            ];
        }

        usort($scopes, fn($a, $b) => strcmp((string)$a['label'], (string)$b['label']));
        return $scopes;
    }

    public function resolveTeamScope(int $wpUserId, bool $isAdmin, int $teamId): ?array
    {
        foreach ($this->listVisibleTeamScopes($wpUserId, $isAdmin) as $s) {
            if ((int)$s['scope_id'] === $teamId) {
                return $s;
            }
        }
        return null;
    }

    public function listAllowedPersonas(?string $maxVisibilityScope, bool $isAdmin): array
    {
        if ($isAdmin) {
            return ['manager', 'support', 'pm', 'sales', 'timesheets'];
        }

        if ($maxVisibilityScope === null) {
            return [];
        }

        $rank = ['TEAM' => 2, 'MANAGERIAL' => 3, 'ADMIN' => 4];
        $r = $rank[$maxVisibilityScope] ?? 0;

        $out = [];
        if ($r >= 3) {
            $out[] = 'manager';
        }
        if ($r >= 2) {
            $out[] = 'support';
            $out[] = 'pm';
            $out[] = 'timesheets';
        }
        return $out;
    }

    private function managedTeamIds(array $teamsById, int $employeeId): array
    {
        $managed = [];
        foreach ($teamsById as $team) {
            if ($team->managerId() === $employeeId || $team->escalationManagerId() === $employeeId) {
                $managed[] = $team->id();
                foreach ($this->descendantTeamIds($teamsById, $team->id()) as $childId) {
                    $managed[] = $childId;
                }
            }
        }
        return array_values(array_unique(array_filter(array_map('intval', $managed))));
    }

    private function descendantTeamIds(array $teamsById, int $parentId): array
    {
        $children = [];
        foreach ($teamsById as $team) {
            if ($team->parentTeamId() === $parentId && $team->id() !== null) {
                $children[] = $team->id();
                foreach ($this->descendantTeamIds($teamsById, $team->id()) as $childId) {
                    $children[] = $childId;
                }
            }
        }
        return $children;
    }

    private function maxScope(?string $existing, string $incoming): string
    {
        $rank = ['TEAM' => 2, 'MANAGERIAL' => 3, 'ADMIN' => 4];
        if ($existing === null) {
            return $incoming;
        }
        return ($rank[$incoming] > $rank[$existing]) ? $incoming : $existing;
    }
}

