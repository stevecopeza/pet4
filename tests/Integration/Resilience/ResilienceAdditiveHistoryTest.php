<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Resilience;

use Pet\Application\Resilience\Query\TeamWorkloadConcentrationQuery;
use Pet\Application\Resilience\Service\ResilienceAnalysisGenerator;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Entity\Team;
use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Domain\Work\Service\CapacityCalendar;
use Pet\Infrastructure\Persistence\Repository\SqlResilienceAnalysisRunRepository;
use Pet\Infrastructure\Persistence\Repository\SqlResilienceSignalRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class ResilienceAdditiveHistoryTest extends TestCase
{
    public function testRepeatGenerationCreatesNewRunAndDeactivatesPriorSignals(): void
    {
        $wpdb = new WpdbStub();
        $p = $wpdb->prefix;

        $wpdb->query("CREATE TABLE {$p}pet_resilience_analysis_runs (
            id TEXT PRIMARY KEY,
            scope_type TEXT NOT NULL,
            scope_id INTEGER NOT NULL,
            version_number INTEGER NOT NULL,
            status TEXT NOT NULL,
            started_at TEXT NOT NULL,
            completed_at TEXT NULL,
            generated_by INTEGER NULL,
            summary_json TEXT NULL,
            created_at TEXT NOT NULL
        )");

        $wpdb->query("CREATE TABLE {$p}pet_resilience_signals (
            id TEXT PRIMARY KEY,
            analysis_run_id TEXT NOT NULL,
            scope_type TEXT NOT NULL,
            scope_id INTEGER NOT NULL,
            signal_type TEXT NOT NULL,
            severity TEXT NOT NULL,
            title TEXT NOT NULL,
            summary TEXT NOT NULL,
            employee_id INTEGER NULL,
            team_id INTEGER NULL,
            role_id INTEGER NULL,
            source_entity_type TEXT NULL,
            source_entity_id TEXT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            resolved_at TEXT NULL,
            metadata_json TEXT NULL
        )");

        $runRepo = new SqlResilienceAnalysisRunRepository($wpdb);
        $signalRepo = new SqlResilienceSignalRepository($wpdb);

        $employees = [
            new Employee(200, 'Liam', 'Ng', 'liam@example.com', 10, 'active', null, null, null, null, [], [7]),
        ];
        $employeeRepo = new class($employees) implements EmployeeRepository {
            public function __construct(private array $employees) {}
            public function save(Employee $employee): void {}
            public function findById(int $id): ?Employee { foreach ($this->employees as $e) { if ($e->id() === $id) return $e; } return null; }
            public function findByWpUserId(int $wpUserId): ?Employee { foreach ($this->employees as $e) { if ($e->wpUserId() === $wpUserId) return $e; } return null; }
            public function findAll(): array { return $this->employees; }
        };

        $teamRepo = new class implements TeamRepository {
            public function find(int $id): ?Team { return $id === 7 ? new Team('On-Call', 7) : null; }
            public function findAll(bool $includeArchived = false): array { return [new Team('On-Call', 7)]; }
            public function save(Team $team): void {}
            public function delete(int $id): void {}
            public function findByParent(int $parentId): array { return []; }
        };

        $capacity = new class extends CapacityCalendar {
            public function __construct() {}
            public function getUserDailyUtilization(int $employeeId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
            {
                return [
                    ['date' => $start->format('Y-m-d'), 'utilization_pct' => 160.0],
                ];
            }
        };

        $workloadQuery = new class extends TeamWorkloadConcentrationQuery {
            public function __construct() {}
            public function countOpenAssignedByUserForTeam(int $teamId): array
            {
                return ['200' => 12, '201' => 3];
            }
        };

        $generator = new ResilienceAnalysisGenerator(
            $runRepo,
            $signalRepo,
            $employeeRepo,
            $teamRepo,
            $capacity,
            $workloadQuery
        );

        $runId1 = $generator->generateForTeam(7, null);
        $runId2 = $generator->generateForTeam(7, null);

        $runs = $runRepo->findByScope('team', 7, 10);
        $this->assertCount(2, $runs);
        $this->assertSame(2, $runs[0]->versionNumber());
        $this->assertSame(1, $runs[1]->versionNumber());

        $signalsRun1 = $signalRepo->findByAnalysisRunId($runId1);
        $signalsRun2 = $signalRepo->findByAnalysisRunId($runId2);
        $this->assertNotEmpty($signalsRun1);
        $this->assertNotEmpty($signalsRun2);

        foreach ($signalsRun1 as $s) {
            $this->assertSame('INACTIVE', $s->status());
        }
        foreach ($signalsRun2 as $s) {
            $this->assertSame('ACTIVE', $s->status());
        }

        $active = $signalRepo->findActiveByScope('team', 7);
        $this->assertCount(count($signalsRun2), $active);
    }
}

