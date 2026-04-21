<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Delivery;

use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\ValueObject\ProjectState;
use Pet\Infrastructure\Persistence\Repository\SqlProjectRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class SqlProjectRepositoryTaskWriteSuppressionTest extends TestCase
{
    public function testSaveDoesNotPersistLegacyTaskRowsInTicketOnlyModel(): void
    {
        $wpdb = new WpdbStub();
        $p = $wpdb->prefix;

        $wpdb->query(
            "CREATE TABLE {$p}pet_projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                source_quote_id INTEGER NULL,
                name TEXT NOT NULL,
                sold_hours REAL NOT NULL DEFAULT 0,
                state TEXT NOT NULL,
                sold_value REAL NOT NULL DEFAULT 0,
                start_date TEXT NULL,
                end_date TEXT NULL,
                malleable_schema_version INTEGER NULL,
                malleable_data TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL,
                archived_at TEXT NULL
            )"
        );
        $wpdb->query(
            "CREATE TABLE {$p}pet_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                estimated_hours REAL NOT NULL DEFAULT 0,
                is_completed INTEGER NOT NULL DEFAULT 0,
                role_id INTEGER NULL,
                created_at TEXT NOT NULL
            )"
        );

        $repo = $this->newRepositoryWithStub($wpdb);
        $project = new Project(
            99,
            'Tickets-only project',
            8.0,
            701,
            ProjectState::planned(),
            12000.0,
            null,
            null,
            null,
            null,
            [],
            new \DateTimeImmutable('2026-03-24 10:30:00'),
            null,
            null,
            [new Task('Legacy task should not persist', 4.0, false, null, 5)]
        );

        $repo->save($project);

        self::assertSame(1, (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}pet_projects"));
        self::assertSame(
            0,
            (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}pet_tasks"),
            'Project save must not create pet_tasks rows in ticket-only execution.'
        );

        $savedProjectId = (int)$wpdb->insert_id;
        $loaded = $repo->findById($savedProjectId);
        self::assertNotNull($loaded);
        self::assertSame([], $loaded->tasks());
    }

    private function newRepositoryWithStub(WpdbStub $wpdb): SqlProjectRepository
    {
        $ref = new \ReflectionClass(SqlProjectRepository::class);
        /** @var SqlProjectRepository $repo */
        $repo = $ref->newInstanceWithoutConstructor();

        $wpdbProp = $ref->getProperty('wpdb');
        $wpdbProp->setAccessible(true);
        $wpdbProp->setValue($repo, $wpdb);

        $projectsTableProp = $ref->getProperty('projectsTable');
        $projectsTableProp->setAccessible(true);
        $projectsTableProp->setValue($repo, $wpdb->prefix . 'pet_projects');

        $tasksTableProp = $ref->getProperty('tasksTable');
        $tasksTableProp->setAccessible(true);
        $tasksTableProp->setValue($repo, $wpdb->prefix . 'pet_tasks');

        return $repo;
    }
}
