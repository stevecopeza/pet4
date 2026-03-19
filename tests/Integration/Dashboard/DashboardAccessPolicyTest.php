<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Dashboard;

use Pet\Domain\Dashboard\Service\DashboardAccessPolicy;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Entity\Team;
use Pet\Domain\Team\Repository\TeamRepository;
use PHPUnit\Framework\TestCase;

final class DashboardAccessPolicyTest extends TestCase
{
    public function testNonAdminSeesMemberTeamsAndManagedTeamsIncludingDescendants(): void
    {
        $employee = new Employee(100, 'A', 'B', 'a@example.com', 10, 'active', null, null, null, null, [], [3]);

        $teams = [
            new Team('Support', 1, null, 10, null),
            new Team('Support L2', 2, 1, null, null),
            new Team('Delivery', 3, null, 99, null),
        ];

        $employeeRepo = new class($employee) implements EmployeeRepository {
            public function __construct(private Employee $employee) {}
            public function save(Employee $employee): void {}
            public function findById(int $id): ?Employee { return $id === $this->employee->id() ? $this->employee : null; }
            public function findByWpUserId(int $wpUserId): ?Employee { return $wpUserId === $this->employee->wpUserId() ? $this->employee : null; }
            public function findAll(): array { return [$this->employee]; }
        };

        $teamRepo = new class($teams) implements TeamRepository {
            public function __construct(private array $teams) {}
            public function find(int $id): ?Team { foreach ($this->teams as $t) { if ($t->id() === $id) return $t; } return null; }
            public function findAll(bool $includeArchived = false): array { return $this->teams; }
            public function save(Team $team): void {}
            public function delete(int $id): void {}
            public function findByParent(int $parentId): array { return array_values(array_filter($this->teams, fn($t) => $t->parentTeamId() === $parentId)); }
        };

        $policy = new DashboardAccessPolicy($employeeRepo, $teamRepo);
        $scopes = $policy->listVisibleTeamScopes(100, false);

        $byId = [];
        foreach ($scopes as $s) {
            $byId[(int)$s['scope_id']] = $s;
        }

        $this->assertSame('MANAGERIAL', $byId[1]['visibility_scope']);
        $this->assertSame('MANAGERIAL', $byId[2]['visibility_scope']);
        $this->assertSame('TEAM', $byId[3]['visibility_scope']);
    }

    public function testAdminSeesAllTeamsAsAdmin(): void
    {
        $employeeRepo = new class implements EmployeeRepository {
            public function save(Employee $employee): void {}
            public function findById(int $id): ?Employee { return null; }
            public function findByWpUserId(int $wpUserId): ?Employee { return null; }
            public function findAll(): array { return []; }
        };

        $teams = [
            new Team('A', 1),
            new Team('B', 2),
        ];
        $teamRepo = new class($teams) implements TeamRepository {
            public function __construct(private array $teams) {}
            public function find(int $id): ?Team { foreach ($this->teams as $t) { if ($t->id() === $id) return $t; } return null; }
            public function findAll(bool $includeArchived = false): array { return $this->teams; }
            public function save(Team $team): void {}
            public function delete(int $id): void {}
            public function findByParent(int $parentId): array { return []; }
        };

        $policy = new DashboardAccessPolicy($employeeRepo, $teamRepo);
        $scopes = $policy->listVisibleTeamScopes(100, true);

        $this->assertCount(2, $scopes);
        $this->assertSame('ADMIN', $scopes[0]['visibility_scope']);
        $this->assertSame('ADMIN', $scopes[1]['visibility_scope']);
    }
}

