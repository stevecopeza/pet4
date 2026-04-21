<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\System;
use Pet\Application\System\Service\DemoPurgeService;

use Pet\Application\System\Service\SeedRegistryDiagnosticsService;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class SeedRegistryDiagnosticsServiceTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $this->createSchema();
    }

    public function testDiagnosticsReturnsRunsDistributionAndSummary(): void
    {
        $this->seedRun('run-a', '2026-03-23 10:00:00');
        $this->seedRun('run-b', '2026-03-23 11:00:00');
        $diagnostics = (new SeedRegistryDiagnosticsService(new DemoPurgeService($this->wpdb), $this->wpdb))->diagnostics();

        $this->assertArrayHasKey('runs', $diagnostics);
        $this->assertArrayHasKey('table_distribution', $diagnostics);
        $this->assertArrayHasKey('integrity_issues', $diagnostics);
        $this->assertArrayHasKey('registry_summary', $diagnostics);
        $this->assertCount(2, $diagnostics['runs']);
        $this->assertSame('run-b', $diagnostics['runs'][0]['run_id']);
        $this->assertSame(2, $diagnostics['registry_summary']['runs_count']);
        $this->assertSame(2, $diagnostics['registry_summary']['active_runs_count']);
        $this->assertGreaterThan(0, $diagnostics['registry_summary']['total_registry_rows']);
    }

    public function testDiagnosticsDetectsDuplicatePairsAndMissingRegistryLinks(): void
    {
        $tables = $this->seedRun('run-problem', '2026-03-23 12:00:00');

        $this->wpdb->insert($tables['skills'], ['employee_id' => 1, 'skill_id' => 10]);
        $this->wpdb->insert($tables['certs'], ['employee_id' => 1, 'certification_id' => 20]);

        $registryTable = $this->wpdb->prefix . 'pet_demo_seed_registry';
        $this->wpdb->insert($registryTable, [
            'seed_run_id' => 'run-problem',
            'table_name' => $tables['tickets'],
            'row_id' => '99999',
            'created_at' => '2026-03-23 12:00:00',
            'purge_status' => 'ACTIVE',
        ]);

        $diagnostics = (new SeedRegistryDiagnosticsService(new DemoPurgeService($this->wpdb), $this->wpdb))->diagnostics();
        $issuesByType = [];
        foreach ($diagnostics['integrity_issues'] as $issue) {
            $issuesByType[(string)$issue['type']] = (int)$issue['count'];
        }
        $this->assertSame(0, $issuesByType['duplicate_employee_emails'] ?? 0);

        $this->assertGreaterThan(0, $issuesByType['duplicate_skill_pairs'] ?? 0);
        $this->assertGreaterThan(0, $issuesByType['duplicate_certification_pairs'] ?? 0);
        $this->assertGreaterThan(0, (int)$diagnostics['registry_summary']['rows_linked_to_missing_entities']);
    }

    private function createSchema(): void
    {
        $p = $this->wpdb->prefix;
        $this->wpdb->query("CREATE TABLE {$p}pet_demo_seed_registry (id INTEGER PRIMARY KEY AUTOINCREMENT, seed_run_id TEXT, table_name TEXT, row_id TEXT, created_at TEXT, purge_status TEXT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_customers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_quotes (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_projects (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_tickets (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_time_entries (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_person_skills (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id INTEGER, skill_id INTEGER)");
        $this->wpdb->query("CREATE TABLE {$p}pet_person_certifications (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id INTEGER, certification_id INTEGER)");
        $this->wpdb->query('CREATE TABLE ' . $p . 'pet_team_members (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER, employee_id INTEGER)');
        $this->wpdb->query('CREATE TABLE ' . $p . 'pet_employees (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NULL)');
        $this->wpdb->query('CREATE TABLE ' . $p . 'pet_teams (id INTEGER PRIMARY KEY AUTOINCREMENT)');
    }

    /**
     * @return array<string, string>
     */
    private function seedRun(string $runId, string $createdAt): array
    {
        $p = $this->wpdb->prefix;
        $tables = [
            'customers' => "{$p}pet_customers",
            'quotes' => "{$p}pet_quotes",
            'projects' => "{$p}pet_projects",
            'tickets' => "{$p}pet_tickets",
            'time_entries' => "{$p}pet_time_entries",
            'skills' => "{$p}pet_person_skills",
            'certs' => "{$p}pet_person_certifications",
            'registry' => "{$p}pet_demo_seed_registry",
        ];

        $this->wpdb->insert($tables['customers'], ['name' => "Customer {$runId}"]);
        $customerId = (int)$this->wpdb->insert_id;
        $this->wpdb->query("INSERT INTO {$tables['quotes']} DEFAULT VALUES");
        $quoteId = (int)$this->wpdb->get_var("SELECT id FROM {$tables['quotes']} ORDER BY id DESC LIMIT 1");
        $this->wpdb->query("INSERT INTO {$tables['projects']} DEFAULT VALUES");
        $projectId = (int)$this->wpdb->get_var("SELECT id FROM {$tables['projects']} ORDER BY id DESC LIMIT 1");
        $this->wpdb->query("INSERT INTO {$tables['tickets']} DEFAULT VALUES");
        $ticketId = (int)$this->wpdb->get_var("SELECT id FROM {$tables['tickets']} ORDER BY id DESC LIMIT 1");
        $this->wpdb->query("INSERT INTO {$tables['time_entries']} DEFAULT VALUES");
        $timeEntryId = (int)$this->wpdb->get_var("SELECT id FROM {$tables['time_entries']} ORDER BY id DESC LIMIT 1");
        $this->wpdb->insert($tables['skills'], ['employee_id' => 1, 'skill_id' => 10]);
        $skillId = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($tables['certs'], ['employee_id' => 1, 'certification_id' => 20]);
        $certId = (int)$this->wpdb->insert_id;

        foreach ([
            [$tables['customers'], $customerId],
            [$tables['quotes'], $quoteId],
            [$tables['projects'], $projectId],
            [$tables['tickets'], $ticketId],
            [$tables['time_entries'], $timeEntryId],
            [$tables['skills'], $skillId],
            [$tables['certs'], $certId],
        ] as [$tableName, $rowId]) {
            $this->wpdb->insert($tables['registry'], [
                'seed_run_id' => $runId,
                'table_name' => (string)$tableName,
                'row_id' => (string)$rowId,
                'created_at' => $createdAt,
                'purge_status' => 'ACTIVE',
            ]);
        }

        return $tables;
    }
}

