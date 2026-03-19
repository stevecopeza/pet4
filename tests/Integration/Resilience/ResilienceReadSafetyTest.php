<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Resilience;

use Pet\Infrastructure\Persistence\Repository\SqlResilienceAnalysisRunRepository;
use Pet\Infrastructure\Persistence\Repository\SqlResilienceSignalRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class ResilienceReadSafetyTest extends TestCase
{
    public function testResilienceReadRepositoriesDoNotWrite(): void
    {
        $wpdb = new class extends WpdbStub {
            public int $writes = 0;
            public function insert(string $table, array $data)
            {
                $this->writes++;
                return parent::insert($table, $data);
            }
            public function update(string $table, array $data, array $where)
            {
                $this->writes++;
                return parent::update($table, $data, $where);
            }
            public function query(string $sql)
            {
                $upper = strtoupper(ltrim($sql));
                if (str_starts_with($upper, 'INSERT') || str_starts_with($upper, 'UPDATE') || str_starts_with($upper, 'DELETE') || str_starts_with($upper, 'REPLACE')) {
                    $this->writes++;
                }
                return parent::query($sql);
            }
        };

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

        $baselineWrites = $wpdb->writes;

        $wpdb->insert($p . 'pet_resilience_analysis_runs', [
            'id' => 'run-1',
            'scope_type' => 'team',
            'scope_id' => 7,
            'version_number' => 1,
            'status' => 'COMPLETED',
            'started_at' => '2026-01-01 00:00:00',
            'completed_at' => '2026-01-01 00:00:00',
            'generated_by' => null,
            'summary_json' => null,
            'created_at' => '2026-01-01 00:00:00',
        ]);
        $wpdb->insert($p . 'pet_resilience_signals', [
            'id' => 'sig-1',
            'analysis_run_id' => 'run-1',
            'scope_type' => 'team',
            'scope_id' => 7,
            'signal_type' => 'team_spof',
            'severity' => 'critical',
            'title' => 'Team SPOF',
            'summary' => 'Only 1',
            'employee_id' => 10,
            'team_id' => 7,
            'role_id' => null,
            'source_entity_type' => null,
            'source_entity_id' => null,
            'status' => 'ACTIVE',
            'created_at' => '2026-01-01 00:00:00',
            'resolved_at' => null,
            'metadata_json' => null,
        ]);

        $runRepo = new SqlResilienceAnalysisRunRepository($wpdb);
        $sigRepo = new SqlResilienceSignalRepository($wpdb);

        $readsBaseline = $wpdb->writes;

        $this->assertNotNull($runRepo->findLatestByScope('team', 7));
        $this->assertSame($readsBaseline, $wpdb->writes);

        $active = $sigRepo->findActiveByScope('team', 7);
        $this->assertCount(1, $active);
        $this->assertSame($readsBaseline, $wpdb->writes);

        $byRun = $sigRepo->findByAnalysisRunId('run-1');
        $this->assertCount(1, $byRun);
        $this->assertSame($readsBaseline, $wpdb->writes);

        $this->assertGreaterThan($baselineWrites, $readsBaseline);
    }
}

