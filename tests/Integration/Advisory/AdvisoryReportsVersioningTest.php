<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Advisory;

use Pet\Application\Advisory\Command\GenerateAdvisoryReportCommand;
use Pet\Application\Advisory\Command\GenerateAdvisoryReportHandler;
use Pet\Application\Advisory\Service\CustomerAdvisorySnapshotQuery;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Infrastructure\Persistence\Repository\SqlAdvisoryReportRepository;
use Pet\Infrastructure\Persistence\Repository\SqlAdvisorySignalRepository;
use Pet\Infrastructure\Persistence\Repository\SqlSettingRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class AdvisoryReportsVersioningTest extends TestCase
{
    public function testGenerateTwiceCreatesAdditiveVersionsAndReadIsWriteSafe(): void
    {
        $spy = new class extends WpdbStub {
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
                if (str_starts_with($upper, 'INSERT') || str_starts_with($upper, 'UPDATE') || str_starts_with($upper, 'DELETE') || str_starts_with($upper, 'ALTER') || str_starts_with($upper, 'CREATE') || str_starts_with($upper, 'DROP') || str_starts_with($upper, 'REPLACE')) {
                    $this->writes++;
                }
                return parent::query($sql);
            }
        };

        $prefix = $spy->prefix;

        $spy->query(
            "CREATE TABLE {$prefix}pet_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT,
                setting_type TEXT,
                description TEXT,
                updated_at TEXT
            )"
        );
        $spy->insert($prefix . 'pet_settings', [
            'setting_key' => 'pet_advisory_reports_enabled',
            'setting_value' => 'true',
            'setting_type' => 'boolean',
            'description' => 'advisory reports',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $spy->query("CREATE TABLE {$prefix}pet_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            site_id INTEGER NULL,
            subject TEXT NOT NULL,
            description TEXT NOT NULL,
            status TEXT NOT NULL,
            priority TEXT NOT NULL,
            resolution_due_at TEXT NULL
        )");

        $spy->query("CREATE TABLE {$prefix}pet_projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            source_quote_id INTEGER NULL,
            name TEXT NOT NULL,
            state TEXT NOT NULL,
            sold_hours REAL NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL,
            archived_at TEXT NULL
        )");

        $spy->query("CREATE TABLE {$prefix}pet_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            estimated_hours REAL NOT NULL DEFAULT 0,
            is_completed INTEGER NOT NULL DEFAULT 0,
            role_id INTEGER NULL,
            created_at TEXT NOT NULL
        )");

        $spy->query("CREATE TABLE {$prefix}pet_work_items (
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
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            revenue REAL NOT NULL DEFAULT 0,
            client_tier INTEGER NOT NULL DEFAULT 1,
            manager_priority_override REAL NOT NULL DEFAULT 0,
            required_role_id INTEGER NULL
        )");

        $spy->query("CREATE TABLE {$prefix}pet_advisory_signals (
            id TEXT PRIMARY KEY,
            work_item_id TEXT NOT NULL,
            signal_type TEXT NOT NULL,
            severity TEXT NOT NULL,
            status TEXT NOT NULL,
            resolved_at TEXT NULL,
            generation_run_id TEXT NULL,
            title TEXT NULL,
            summary TEXT NULL,
            metadata_json TEXT NULL,
            source_entity_type TEXT NULL,
            source_entity_id TEXT NULL,
            customer_id INTEGER NULL,
            site_id INTEGER NULL,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL
        )");

        $spy->query("CREATE TABLE {$prefix}pet_advisory_reports (
            id TEXT PRIMARY KEY,
            report_type TEXT NOT NULL,
            scope_type TEXT NOT NULL,
            scope_id INTEGER NOT NULL,
            version_number INTEGER NOT NULL,
            title TEXT NOT NULL,
            summary TEXT NULL,
            status TEXT NOT NULL,
            generated_at TEXT NOT NULL,
            generated_by INTEGER NULL,
            content_json TEXT NOT NULL,
            source_snapshot_metadata_json TEXT NULL
        )");

        $spy->insert($prefix . 'pet_tickets', [
            'customer_id' => 1,
            'site_id' => null,
            'subject' => 'Example',
            'description' => 'D',
            'status' => 'open',
            'priority' => 'high',
            'resolution_due_at' => null,
        ]);
        $ticketId = (int)$spy->insert_id;

        $spy->insert($prefix . 'pet_work_items', [
            'id' => 'wi-1',
            'source_type' => 'ticket',
            'source_id' => (string)$ticketId,
            'assigned_user_id' => '10',
            'department_id' => 'support',
            'priority_score' => 10,
            'status' => 'active',
        ]);

        $signalsRepo = new SqlAdvisorySignalRepository($spy);
        $signalsRepo->save(new AdvisorySignal(
            'sig-1',
            'wi-1',
            AdvisorySignal::TYPE_SLA_RISK,
            AdvisorySignal::SEVERITY_WARNING,
            'm',
            new \DateTimeImmutable('2026-01-01 00:00:00'),
            'ACTIVE',
            null,
            'run-1'
        ));

        $settingsRepo = new SqlSettingRepository($spy);
        $flags = new FeatureFlagService($settingsRepo);
        $reportsRepo = new SqlAdvisoryReportRepository($spy);
        $snapshot = new CustomerAdvisorySnapshotQuery($spy);
        $handler = new GenerateAdvisoryReportHandler($reportsRepo, $snapshot, $flags);

        $beforeWrites = $spy->writes;
        $snapshot->snapshotForCustomer(1);
        $this->assertSame($beforeWrites, $spy->writes);

        $r1 = $handler->handle(new GenerateAdvisoryReportCommand(1, 'customer_advisory_summary', 99));
        $r2 = $handler->handle(new GenerateAdvisoryReportCommand(1, 'customer_advisory_summary', 99));

        $this->assertSame(1, $r1->versionNumber());
        $this->assertSame(2, $r2->versionNumber());

        $rows = $spy->get_results($spy->prepare(
            "SELECT version_number FROM {$prefix}pet_advisory_reports WHERE report_type = %s AND scope_type = %s AND scope_id = %d ORDER BY version_number ASC",
            'customer_advisory_summary',
            'customer',
            1
        ));
        $this->assertCount(2, $rows);
        $this->assertSame(1, (int)$rows[0]->version_number);
        $this->assertSame(2, (int)$rows[1]->version_number);
    }
}
