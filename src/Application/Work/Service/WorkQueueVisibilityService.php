<?php

declare(strict_types=1);

namespace Pet\Application\Work\Service;

use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Repository\TeamRepository;

class WorkQueueVisibilityService
{
    public function __construct(
        private EmployeeRepository $employeeRepository,
        private TeamRepository $teamRepository
    ) {
    }

    public function listVisibleQueues(int $wpUserId, bool $isAdmin): array
    {
        $queues = [];

        $queues[$this->userQueueKey('support', (string)$wpUserId)] = 'SELF';

        if ($isAdmin) {
            $queues['support:unrouted'] = 'ADMIN';
            foreach ($this->teamRepository->findAll(true) as $team) {
                if ($team->id() === null) {
                    continue;
                }
                $teamId = (string)$team->id();
                $queues[$this->teamQueueKey('support', $teamId)] = 'ADMIN';
                $queues[$this->teamQueueKey('delivery', $teamId)] = 'ADMIN';
            }
            return $this->toDescriptors($queues);
        }

        $employee = $this->employeeRepository->findByWpUserId($wpUserId);
        if ($employee) {
            foreach ($employee->teamIds() as $teamId) {
                $tid = (string)$teamId;
                $queues[$this->teamQueueKey('support', $tid)] = $this->maxScope($queues[$this->teamQueueKey('support', $tid)] ?? null, 'TEAM');
                $queues[$this->teamQueueKey('delivery', $tid)] = $this->maxScope($queues[$this->teamQueueKey('delivery', $tid)] ?? null, 'TEAM');
            }

            $managed = $this->managedTeamIds($employee->id());
            foreach ($managed as $teamId) {
                $tid = (string)$teamId;
                $queues[$this->teamQueueKey('support', $tid)] = $this->maxScope($queues[$this->teamQueueKey('support', $tid)] ?? null, 'MANAGERIAL');
                $queues[$this->teamQueueKey('delivery', $tid)] = $this->maxScope($queues[$this->teamQueueKey('delivery', $tid)] ?? null, 'MANAGERIAL');
            }

            if (!empty($employee->teamIds()) || !empty($managed)) {
                $queues['support:unrouted'] = !empty($managed) ? 'MANAGERIAL' : 'TEAM';
            }
        }

        return $this->toDescriptors($queues);
    }

    private function managedTeamIds(int $employeeId): array
    {
        $teams = $this->teamRepository->findAll(true);
        $managed = [];

        foreach ($teams as $team) {
            if ($team->id() === null) {
                continue;
            }
            if ($team->managerId() === $employeeId || $team->escalationManagerId() === $employeeId) {
                $managed[] = $team->id();
                foreach ($this->descendantTeamIds($teams, $team->id()) as $childId) {
                    $managed[] = $childId;
                }
            }
        }

        return array_values(array_unique($managed));
    }

    private function descendantTeamIds(array $teams, int $parentId): array
    {
        $children = [];
        foreach ($teams as $team) {
            if ($team->parentTeamId() === $parentId && $team->id() !== null) {
                $children[] = $team->id();
                foreach ($this->descendantTeamIds($teams, $team->id()) as $childId) {
                    $children[] = $childId;
                }
            }
        }
        return $children;
    }

    private function teamQueueKey(string $domain, string $teamId): string
    {
        return "{$domain}:team:{$teamId}";
    }

    private function userQueueKey(string $domain, string $userId): string
    {
        return "{$domain}:user:{$userId}";
    }

    private function maxScope(?string $existing, string $incoming): string
    {
        $rank = ['SELF' => 1, 'TEAM' => 2, 'MANAGERIAL' => 3, 'ADMIN' => 4];
        if ($existing === null) {
            return $incoming;
        }
        return ($rank[$incoming] > $rank[$existing]) ? $incoming : $existing;
    }

    private function toDescriptors(array $queueKeyToScope): array
    {
        $out = [];
        foreach ($queueKeyToScope as $key => $scope) {
            $out[] = ['queue_key' => $key, 'visibility_scope' => $scope];
        }
        usort($out, fn($a, $b) => strcmp($a['queue_key'], $b['queue_key']));
        return $out;
    }
}

