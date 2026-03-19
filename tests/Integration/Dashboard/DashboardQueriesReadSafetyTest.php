<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Dashboard;

use Pet\Application\Dashboard\Query\TeamAdvisorySummaryQuery;
use Pet\Application\Dashboard\Query\TeamEscalationSummaryQuery;
use Pet\Application\Dashboard\Query\TeamSupportSummaryQuery;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class DashboardQueriesReadSafetyTest extends TestCase
{
    public function testDashboardQueriesDoNotWrite(): void
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

        $wpdb->query("CREATE TABLE {$p}pet_work_items (
            id TEXT PRIMARY KEY,
            source_type TEXT NOT NULL,
            source_id TEXT NOT NULL,
            assigned_user_id TEXT NULL,
            department_id TEXT NULL,
            sla_time_remaining_minutes INTEGER NULL
        )");
        $wpdb->query("CREATE TABLE {$p}pet_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT NOT NULL
        )");
        $wpdb->query("CREATE TABLE {$p}pet_advisory_signals (
            id TEXT PRIMARY KEY,
            work_item_id TEXT NOT NULL,
            severity TEXT NOT NULL,
            status TEXT NOT NULL
        )");
        $wpdb->query("CREATE TABLE {$p}pet_escalations (
            id TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            source_entity_type TEXT NOT NULL,
            source_entity_id INTEGER NOT NULL,
            severity TEXT NOT NULL
        )");

        $wpdb->insert($p . 'pet_tickets', ['status' => 'open']);
        $ticketId = (int)$wpdb->insert_id;

        $wpdb->insert($p . 'pet_work_items', [
            'id' => 'wi-1',
            'source_type' => 'ticket',
            'source_id' => (string)$ticketId,
            'assigned_user_id' => null,
            'department_id' => '1',
            'sla_time_remaining_minutes' => -5,
        ]);

        $wpdb->insert($p . 'pet_advisory_signals', [
            'id' => 'sig-1',
            'work_item_id' => 'wi-1',
            'severity' => 'warning',
            'status' => 'ACTIVE',
        ]);

        $wpdb->insert($p . 'pet_escalations', [
            'id' => 'esc-1',
            'status' => 'OPEN',
            'source_entity_type' => 'ticket',
            'source_entity_id' => $ticketId,
            'severity' => 'critical',
        ]);

        $baselineWrites = $wpdb->writes;

        $support = new TeamSupportSummaryQuery($wpdb);
        $support->getSummaryForTeam(1);
        $this->assertSame($baselineWrites, $wpdb->writes);

        $advisory = new TeamAdvisorySummaryQuery($wpdb);
        $advisory->getActiveSummaryForTeam(1);
        $this->assertSame($baselineWrites, $wpdb->writes);

        $escalations = new TeamEscalationSummaryQuery($wpdb);
        $escalations->getOpenSummaryForTeam(1);
        $this->assertSame($baselineWrites, $wpdb->writes);
    }
}

