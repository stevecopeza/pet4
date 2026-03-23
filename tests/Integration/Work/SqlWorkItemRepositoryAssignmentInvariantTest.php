<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Work;

use DateTimeImmutable;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class SqlWorkItemRepositoryAssignmentInvariantTest extends TestCase
{
    private WpdbStub $wpdb;
    private SqlWorkItemRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $prefix = $this->wpdb->prefix;
        $this->wpdb->query("CREATE TABLE {$prefix}pet_work_items (
            id TEXT PRIMARY KEY,
            source_type TEXT NOT NULL,
            source_id TEXT NOT NULL,
            assigned_user_id TEXT NULL,
            department_id TEXT NOT NULL,
            assigned_team_id TEXT NULL,
            assignment_mode TEXT NULL,
            queue_key TEXT NULL,
            routing_reason TEXT NULL,
            sla_snapshot_id TEXT NULL,
            sla_time_remaining_minutes INTEGER NULL,
            priority_score REAL NOT NULL DEFAULT 0,
            scheduled_start_utc TEXT NULL,
            scheduled_due_utc TEXT NULL,
            capacity_allocation_percent REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'active',
            escalation_level INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            revenue REAL NOT NULL DEFAULT 0,
            client_tier INTEGER NOT NULL DEFAULT 1,
            manager_priority_override REAL NOT NULL DEFAULT 0,
            required_role_id INTEGER NULL
        )");
        $this->repository = new SqlWorkItemRepository($this->wpdb);
    }

    public function testSaveRejectsDualAssignmentInvariantViolation(): void
    {
        $workItem = WorkItem::create(
            'wi-invariant',
            'ticket',
            '42',
            'support',
            10.0,
            'active',
            new DateTimeImmutable('2026-03-19 20:00:00'),
            null,
            '3',
            null,
            null
        );

        // Simulate corrupted state reaching persistence boundary.
        $ref = new \ReflectionObject($workItem);
        $assignedUserProp = $ref->getProperty('assignedUserId');
        $assignedUserProp->setAccessible(true);
        $assignedUserProp->setValue($workItem, '99');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('both assigned_team_id and assigned_user_id are set');
        $this->repository->save($workItem);
    }

    public function testFindByAssignedUserNormalizesLegacyDualAssignmentRows(): void
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        $this->wpdb->insert($table, [
            'id' => 'wi-legacy-dual',
            'source_type' => 'ticket',
            'source_id' => '77',
            'assigned_user_id' => '1',
            'department_id' => 'support',
            'assigned_team_id' => '3',
            'assignment_mode' => 'TEAM_QUEUE',
            'queue_key' => 'support:team:3',
            'routing_reason' => 'seed_staff_time_capture',
            'sla_snapshot_id' => null,
            'sla_time_remaining_minutes' => null,
            'priority_score' => 42.0,
            'scheduled_start_utc' => null,
            'scheduled_due_utc' => null,
            'capacity_allocation_percent' => 0.0,
            'status' => 'active',
            'escalation_level' => 0,
            'created_at' => '2026-03-19 21:00:22',
            'updated_at' => '2026-03-19 21:00:22',
            'revenue' => 0.0,
            'client_tier' => 1,
            'manager_priority_override' => 0.0,
            'required_role_id' => null,
        ]);

        $items = $this->repository->findByAssignedUser('1');
        self::assertCount(1, $items);
        self::assertSame('1', $items[0]->getAssignedUserId());
        self::assertNull($items[0]->getAssignedTeamId());
        self::assertSame(WorkItem::ASSIGNMENT_MODE_USER_ASSIGNED, $items[0]->getAssignmentMode());
        self::assertSame('support:user:1', $items[0]->getQueueKey());
    }
}

