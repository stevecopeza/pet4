<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Work\Service;

use Pet\Application\Work\Service\WorkQueueVisibilityService;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Entity\Team;
use Pet\Domain\Team\Repository\TeamRepository;
use PHPUnit\Framework\TestCase;

final class WorkQueueVisibilityServiceTest extends TestCase
{
    public function testVisibilityIncludesTeamAndManagerialQueues(): void
    {
        $employeeRepo = new class implements EmployeeRepository {
            public function save(Employee $employee): void {}
            public function findById(int $id): ?Employee { return null; }
            public function findByWpUserId(int $wpUserId): ?Employee
            {
                if ($wpUserId === 5) {
                    return new Employee(5, 'A', 'B', 'a@example.test', 100, teamIds: [3]);
                }
                return null;
            }
            public function findAll(): array { return []; }
        };

        $teamRepo = new class implements TeamRepository {
            public function save(Team $team): void {}
            public function find(int $id): ?Team { return null; }
            public function findAll(bool $includeRemoved = false): array
            {
                return [
                    new Team(name: 'Support', id: 3, managerId: 100, escalationManagerId: 100),
                ];
            }
            public function delete(int $id): void {}
            public function findByParent(int $parentId): array { return []; }
        };

        $svc = new WorkQueueVisibilityService($employeeRepo, $teamRepo);
        $queues = $svc->listVisibleQueues(5, false);
        $map = [];
        foreach ($queues as $q) {
            $map[$q['queue_key']] = $q['visibility_scope'];
        }

        $this->assertSame('SELF', $map['support:user:5']);
        $this->assertSame('MANAGERIAL', $map['support:team:3']);
        $this->assertSame('MANAGERIAL', $map['delivery:team:3']);
        $this->assertSame('MANAGERIAL', $map['support:unrouted']);
        $this->assertSame('MANAGERIAL', $map['delivery:unrouted']);
    }

    public function testVisibilityRestrictionForNonEmployeeUser(): void
    {
        $employeeRepo = new class implements EmployeeRepository {
            public function save(Employee $employee): void {}
            public function findById(int $id): ?Employee { return null; }
            public function findByWpUserId(int $wpUserId): ?Employee { return null; }
            public function findAll(): array { return []; }
        };

        $teamRepo = new class implements TeamRepository {
            public function save(Team $team): void {}
            public function find(int $id): ?Team { return null; }
            public function findAll(bool $includeRemoved = false): array { return []; }
            public function delete(int $id): void {}
            public function findByParent(int $parentId): array { return []; }
        };

        $svc = new WorkQueueVisibilityService($employeeRepo, $teamRepo);
        $queues = $svc->listVisibleQueues(6, false);

        $this->assertCount(1, $queues);
        $this->assertSame('support:user:6', $queues[0]['queue_key']);
        $this->assertSame('SELF', $queues[0]['visibility_scope']);
    }
}
