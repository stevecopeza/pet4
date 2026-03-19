<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Support;

use Pet\Application\Support\Query\SupportTeamOperationalSummaryQuery;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class SupportOperationalReadSafetyTest extends TestCase
{
    public function testSupportOperationalSummaryQueryDoesNotWrite(): void
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
            assigned_team_id TEXT NULL,
            sla_time_remaining_minutes INTEGER NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )");
        $wpdb->query("CREATE TABLE {$p}pet_escalations (
            id TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            source_entity_type TEXT NOT NULL,
            source_entity_id TEXT NOT NULL,
            severity TEXT NOT NULL
        )");

        $wpdb->insert($p . 'pet_work_items', [
            'id' => 'wi-1',
            'source_type' => 'ticket',
            'source_id' => '101',
            'assigned_user_id' => null,
            'assigned_team_id' => '3',
            'sla_time_remaining_minutes' => 10,
            'status' => 'active',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $baselineWrites = $wpdb->writes;
        $q = new SupportTeamOperationalSummaryQuery($wpdb);
        $q->getTeamSummary(3, []);
        $this->assertSame($baselineWrites, $wpdb->writes);
    }
}

