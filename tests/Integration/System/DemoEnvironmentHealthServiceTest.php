<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\System;

use Pet\Application\System\Service\DemoEnvironmentHealthService;
use Pet\Application\System\Service\DemoPurgeService;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class DemoEnvironmentHealthServiceTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $this->createSchema();
    }

    public function testReadinessGreenWhenSingleRunNoDuplicatesNoUntrackedRows(): void
    {
        $this->seedTrackedRun('seed-green');
        $health = $this->healthService()->getHealth();

        $this->assertSame('GREEN', $health['readiness_status']);
        $this->assertSame(['no_immediate_readiness_warnings'], $health['readiness_reasons']);
        $this->assertSame('seed-green', $health['seed']['active_seed_run_id']);
        $this->assertSame(1, $health['seed']['tracked_runs_count']);
        $this->assertSame(0, $health['integrity']['duplicate_skill_pairs']);
        $this->assertSame(0, $health['integrity']['duplicate_certification_pairs']);
        $this->assertFalse($health['environment']['has_untracked_rows']);
    }

    public function testReadinessAmberWhenMultipleTrackedRunsExist(): void
    {
        $this->seedTrackedRun('seed-old', '2026-03-23 10:00:00');
        $this->seedTrackedRun('seed-new', '2026-03-23 11:00:00');

        $health = $this->healthService()->getHealth();
        $this->assertSame('AMBER', $health['readiness_status']);
        $this->assertSame(2, $health['seed']['tracked_runs_count']);
        $this->assertTrue($health['flags']['has_contamination_risk']);
        $this->assertContains('multiple_active_seed_runs', $health['readiness_reasons']);
    }

    public function testReadinessRedWhenDuplicateStaffMetadataPairsDetected(): void
    {
        $this->seedTrackedRun('seed-red');
        $skillsTable = $this->wpdb->prefix . 'pet_person_skills';
        $certsTable = $this->wpdb->prefix . 'pet_person_certifications';
        $this->wpdb->insert($skillsTable, ['employee_id' => 1, 'skill_id' => 10]);
        $this->wpdb->insert($certsTable, ['employee_id' => 1, 'certification_id' => 20]);

        $health = $this->healthService()->getHealth();
        $this->assertSame('RED', $health['readiness_status']);
        $this->assertGreaterThan(0, $health['integrity']['duplicate_skill_pairs']);
        $this->assertGreaterThan(0, $health['integrity']['duplicate_certification_pairs']);
        $this->assertTrue($health['flags']['has_duplicate_staff_metadata_pairs']);
        $this->assertContains('duplicate_staff_metadata_pairs', $health['readiness_reasons']);
    }

    private function healthService(): DemoEnvironmentHealthService
    {
        return new DemoEnvironmentHealthService(new DemoPurgeService($this->wpdb), $this->wpdb);
    }

    private function createSchema(): void
    {
        $p = $this->wpdb->prefix;
        $this->wpdb->query("CREATE TABLE {$p}pet_demo_seed_registry (id INTEGER PRIMARY KEY AUTOINCREMENT, seed_run_id TEXT, table_name TEXT, row_id TEXT, created_at TEXT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_employees (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_person_skills (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id INTEGER, skill_id INTEGER)");
        $this->wpdb->query("CREATE TABLE {$p}pet_person_certifications (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id INTEGER, certification_id INTEGER)");
        $this->wpdb->query("CREATE TABLE {$p}pet_quotes (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_projects (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_tickets (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_time_entries (id INTEGER PRIMARY KEY AUTOINCREMENT)");
    }

    private function seedTrackedRun(string $runId, string $createdAt = '2026-03-23 12:00:00'): void
    {
        $p = $this->wpdb->prefix;
        $employeesTable = "{$p}pet_employees";
        $skillsTable = "{$p}pet_person_skills";
        $certsTable = "{$p}pet_person_certifications";
        $quotesTable = "{$p}pet_quotes";
        $projectsTable = "{$p}pet_projects";
        $ticketsTable = "{$p}pet_tickets";
        $timeTable = "{$p}pet_time_entries";
        $registryTable = "{$p}pet_demo_seed_registry";

        $this->wpdb->insert($employeesTable, ['email' => "{$runId}@example.com"]);
        $employeeId = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($skillsTable, ['employee_id' => $employeeId, 'skill_id' => 10]);
        $skillId = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($certsTable, ['employee_id' => $employeeId, 'certification_id' => 20]);
        $certId = (int)$this->wpdb->insert_id;
        $this->wpdb->query("INSERT INTO $quotesTable DEFAULT VALUES");
        $quoteId = (int)$this->wpdb->get_var("SELECT id FROM $quotesTable ORDER BY id DESC LIMIT 1");
        $this->wpdb->query("INSERT INTO $projectsTable DEFAULT VALUES");
        $projectId = (int)$this->wpdb->get_var("SELECT id FROM $projectsTable ORDER BY id DESC LIMIT 1");
        $this->wpdb->query("INSERT INTO $ticketsTable DEFAULT VALUES");
        $ticketId = (int)$this->wpdb->get_var("SELECT id FROM $ticketsTable ORDER BY id DESC LIMIT 1");
        $this->wpdb->query("INSERT INTO $timeTable DEFAULT VALUES");
        $timeId = (int)$this->wpdb->get_var("SELECT id FROM $timeTable ORDER BY id DESC LIMIT 1");

        foreach ([
            [$employeesTable, $employeeId],
            [$skillsTable, $skillId],
            [$certsTable, $certId],
            [$quotesTable, $quoteId],
            [$projectsTable, $projectId],
            [$ticketsTable, $ticketId],
            [$timeTable, $timeId],
        ] as [$tableName, $rowId]) {
            $this->wpdb->insert($registryTable, [
                'seed_run_id' => $runId,
                'table_name' => (string)$tableName,
                'row_id' => (string)$rowId,
                'created_at' => $createdAt,
            ]);
        }
    }
}

