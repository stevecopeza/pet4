<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\System;

use Pet\Application\System\Service\CleanDemoBaselineService;
use Pet\Application\System\Service\DemoPurgeService;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class CleanDemoBaselineServiceTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $this->createBaseTables();
    }

    public function testCleanBaselineWithNoPriorRunsStillSeedsFreshBaseline(): void
    {
        $seedService = $this->createSeedServiceStub();
        $purgeService = new DemoPurgeService($this->wpdb);
        $service = new CleanDemoBaselineService($purgeService, $seedService, $this->wpdb);

        $result = $service->run('demo_full');

        $this->assertSame('PASS', $result['overall']);
        $this->assertSame(0, (int)$result['purge']['active_runs_discovered']);
        $this->assertSame(1, (int)$result['registry']['active_seed_runs']);
        $this->assertNotEmpty($result['seed']['seed_run_id'] ?? '');
        $this->assertSame([], $result['contract']['violations']);
        $this->assertSame(1, (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_customers"));
        $this->assertSame(1, $seedService->calls);
    }

    public function testCleanBaselinePurgesMultipleTrackedRunsInNewestFirstOrderThenSeedsOnce(): void
    {
        $customersTable = $this->wpdb->prefix . 'pet_customers';
        $registryTable = $this->wpdb->prefix . 'pet_demo_seed_registry';

        $this->wpdb->insert($customersTable, ['name' => 'Run A customer', 'created_at' => '2026-03-23 10:00:00']);
        $rowA = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($registryTable, [
            'seed_run_id' => 'run-a',
            'table_name' => $customersTable,
            'row_id' => (string)$rowA,
            'created_at' => '2026-03-23 10:00:00',
            'purge_status' => 'ACTIVE',
            'purged_at' => null,
        ]);

        $this->wpdb->insert($customersTable, ['name' => 'Run B customer', 'created_at' => '2026-03-23 11:00:00']);
        $rowB = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($registryTable, [
            'seed_run_id' => 'run-b',
            'table_name' => $customersTable,
            'row_id' => (string)$rowB,
            'created_at' => '2026-03-23 11:00:00',
            'purge_status' => 'ACTIVE',
            'purged_at' => null,
        ]);

        $seedService = $this->createSeedServiceStub();
        $purgeService = new DemoPurgeService($this->wpdb);
        $service = new CleanDemoBaselineService($purgeService, $seedService, $this->wpdb);
        $result = $service->run('demo_full');

        $this->assertSame('PASS', $result['overall']);
        $this->assertSame(2, (int)$result['purge']['active_runs_discovered']);
        $this->assertCount(2, $result['purge']['purged_runs']);
        $this->assertSame('run-b', (string)$result['purge']['purged_runs'][0]['seed_run_id']);
        $this->assertSame('run-a', (string)$result['purge']['purged_runs'][1]['seed_run_id']);
        $this->assertSame(0, (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $registryTable WHERE seed_run_id IN (%s, %s)",
            ['run-a', 'run-b']
        )));
        $this->assertSame(1, (int)$result['registry']['active_seed_runs']);
        $this->assertSame([], $result['contract']['violations']);
        $this->assertSame(1, $seedService->calls);
    }

    public function testCleanBaselineDoesNotRemoveNonSeededData(): void
    {
        $customersTable = $this->wpdb->prefix . 'pet_customers';
        $registryTable = $this->wpdb->prefix . 'pet_demo_seed_registry';

        $this->wpdb->insert($customersTable, ['name' => 'Non Seeded Customer', 'created_at' => '2026-03-23 09:00:00']);
        $nonSeededId = (int)$this->wpdb->insert_id;

        $this->wpdb->insert($customersTable, ['name' => 'Tracked Seeded Customer', 'created_at' => '2026-03-23 10:00:00']);
        $seededId = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($registryTable, [
            'seed_run_id' => 'run-seeded',
            'table_name' => $customersTable,
            'row_id' => (string)$seededId,
            'created_at' => '2026-03-23 10:00:00',
            'purge_status' => 'ACTIVE',
            'purged_at' => null,
        ]);

        $seedService = $this->createSeedServiceStub();
        $service = new CleanDemoBaselineService(new DemoPurgeService($this->wpdb), $seedService, $this->wpdb);
        $service->run('demo_full');

        $this->assertSame(1, (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $customersTable WHERE id = %d",
            $nonSeededId
        )));
        $this->assertSame(0, (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $customersTable WHERE id = %d",
            $seededId
        )));
    }

    public function testCleanBaselineSummaryIsCoherentAndEndsWithFreshRun(): void
    {
        $customersTable = $this->wpdb->prefix . 'pet_customers';
        $registryTable = $this->wpdb->prefix . 'pet_demo_seed_registry';

        $this->wpdb->insert($customersTable, ['name' => 'Old seeded', 'created_at' => '2026-03-23 08:00:00']);
        $oldId = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($registryTable, [
            'seed_run_id' => 'old-run',
            'table_name' => $customersTable,
            'row_id' => (string)$oldId,
            'created_at' => '2026-03-23 08:00:00',
            'purge_status' => 'ACTIVE',
            'purged_at' => null,
        ]);

        $seedService = $this->createSeedServiceStub();
        $result = (new CleanDemoBaselineService(new DemoPurgeService($this->wpdb), $seedService, $this->wpdb))->run('demo_full');

        $this->assertArrayHasKey('operation', $result);
        $this->assertArrayHasKey('purge', $result);
        $this->assertArrayHasKey('seed', $result);
        $this->assertArrayHasKey('counts', $result);
        $this->assertArrayHasKey('registry', $result);
        $this->assertSame('clean_demo_baseline', (string)$result['operation']);
        $this->assertNotEmpty($result['seed']['seed_run_id'] ?? '');
        $this->assertSame(1, (int)$result['registry']['active_seed_runs']);
        $this->assertSame(0, (int)$result['purge']['purged_runs'][0]['registry_rows_remaining']);
        $this->assertSame(1, (int)$result['counts']['seed_registry_rows']);
        $this->assertSame([], $result['contract']['violations']);
    }

    private function createBaseTables(): void
    {
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_demo_seed_registry (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seed_run_id TEXT,
                table_name TEXT,
                row_id TEXT,
                created_at TEXT,
                purge_status TEXT DEFAULT 'ACTIVE',
                purged_at TEXT
            )"
        );
    }

    private function createSeedServiceStub(): object
    {
        return new class($this->wpdb) {
            private WpdbStub $db;
            public int $calls = 0;

            public function __construct(WpdbStub $wpdb)
            {
                $this->db = $wpdb;
            }

            public function __invoke(string $seedRunId, string $seedProfile = 'demo_full'): array
            {
                $this->calls++;
                $customersTable = $this->db->prefix . 'pet_customers';
                $registryTable = $this->db->prefix . 'pet_demo_seed_registry';
                $createdAt = '2026-03-23 12:00:00';
                $this->db->insert($customersTable, [
                    'name' => 'Fresh Seeded Customer ' . $this->calls,
                    'created_at' => $createdAt,
                ]);
                $customerId = (int)$this->db->insert_id;
                $this->db->insert($registryTable, [
                    'seed_run_id' => $seedRunId,
                    'table_name' => $customersTable,
                    'row_id' => (string)$customerId,
                    'created_at' => $createdAt,
                    'purge_status' => 'ACTIVE',
                    'purged_at' => null,
                ]);
                return [
                    'customers' => 1,
                    'seed_profile' => $seedProfile,
                    'seeded_customer_id' => $customerId,
                ];
            }
        };
    }
}

