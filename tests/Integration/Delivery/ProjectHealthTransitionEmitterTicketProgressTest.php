<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Delivery;

use Pet\Application\Delivery\Service\ProjectHealthTransitionEmitter;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\ValueObject\ProjectState;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class ProjectHealthTransitionEmitterTicketProgressTest extends TestCase
{
    private $previousWpdb;
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->previousWpdb = $wpdb ?? null;
        $this->wpdb = new WpdbStub();
        $wpdb = $this->wpdb;

        $p = $this->wpdb->prefix;
        $this->wpdb->query(
            "CREATE TABLE {$p}pet_tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                lifecycle_owner TEXT NULL,
                is_rollup INTEGER NULL
            )"
        );
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->previousWpdb;
        parent::tearDown();
    }

    public function testHealthProgressUsesDeliveryTicketCompletionWhenTicketsExist(): void
    {
        $p = $this->wpdb->prefix;
        $this->wpdb->insert($p . 'pet_tickets', [
            'project_id' => 501,
            'status' => 'planned',
            'lifecycle_owner' => 'project',
            'is_rollup' => 0,
        ]);
        $this->wpdb->insert($p . 'pet_tickets', [
            'project_id' => 501,
            'status' => 'completed',
            'lifecycle_owner' => 'project',
            'is_rollup' => 0,
        ]);

        $project = new Project(
            44,
            'Health project',
            10.0,
            9001,
            ProjectState::active(),
            1000.0,
            null,
            null,
            501,
            null,
            [],
            new \DateTimeImmutable('2026-03-24 10:00:00'),
            null,
            null,
            [
                new Task('Legacy A', 1.0, true, 1, 7),
                new Task('Legacy B', 1.0, true, 2, 8),
            ]
        );

        $health = $this->computeHealth($project, 2.0);

        self::assertSame('tickets', $health['metadata']['progress_source']);
        self::assertSame(2, $health['metadata']['delivery_ticket_total']);
        self::assertSame(1, $health['metadata']['delivery_ticket_completed']);
        self::assertSame(50, $health['metadata']['progress_pct']);
    }

    public function testHealthProgressFallsBackToLegacyTasksOnlyWhenNoTicketsExist(): void
    {
        $project = new Project(
            44,
            'Legacy fallback',
            10.0,
            9002,
            ProjectState::active(),
            1000.0,
            null,
            null,
            777,
            null,
            [],
            new \DateTimeImmutable('2026-03-24 10:00:00'),
            null,
            null,
            [
                new Task('Legacy done', 1.0, true, 1, 7),
                new Task('Legacy todo', 1.0, false, 2, 8),
            ]
        );

        $health = $this->computeHealth($project, 2.0);

        self::assertSame('legacy_tasks', $health['metadata']['progress_source']);
        self::assertSame(2, $health['metadata']['delivery_ticket_total']);
        self::assertSame(1, $health['metadata']['delivery_ticket_completed']);
        self::assertSame(50, $health['metadata']['progress_pct']);
    }

    private function computeHealth(Project $project, float $hoursUsed): array
    {
        $emitter = new ProjectHealthTransitionEmitter();
        $method = new \ReflectionMethod(ProjectHealthTransitionEmitter::class, 'computeHealth');
        $method->setAccessible(true);
        return $method->invoke($emitter, $project, $hoursUsed);
    }
}
