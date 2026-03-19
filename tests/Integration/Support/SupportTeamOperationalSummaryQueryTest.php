<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Support;

use Pet\Application\Support\Query\SupportTeamOperationalSummaryQuery;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class SupportTeamOperationalSummaryQueryTest extends TestCase
{
    public function testSummaryComputesCountsSlaAndEscalations(): void
    {
        $wpdb = new WpdbStub();
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

        $now = '2026-01-01 00:00:00';
        $old = '2025-12-01 00:00:00';

        $wpdb->insert($p . 'pet_work_items', [
            'id' => 'wi-team',
            'source_type' => 'ticket',
            'source_id' => '101',
            'assigned_user_id' => null,
            'assigned_team_id' => '3',
            'sla_time_remaining_minutes' => 30,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $wpdb->insert($p . 'pet_work_items', [
            'id' => 'wi-user',
            'source_type' => 'ticket',
            'source_id' => '102',
            'assigned_user_id' => '10',
            'assigned_team_id' => null,
            'sla_time_remaining_minutes' => -15,
            'status' => 'waiting',
            'created_at' => $old,
            'updated_at' => $now,
        ]);

        $wpdb->insert($p . 'pet_work_items', [
            'id' => 'wi-unrouted',
            'source_type' => 'ticket',
            'source_id' => '103',
            'assigned_user_id' => null,
            'assigned_team_id' => null,
            'sla_time_remaining_minutes' => null,
            'status' => 'active',
            'created_at' => $old,
            'updated_at' => $now,
        ]);

        $wpdb->insert($p . 'pet_escalations', [
            'id' => 'esc-1',
            'status' => 'OPEN',
            'source_entity_type' => 'ticket',
            'source_entity_id' => '102',
            'severity' => 'critical',
        ]);

        $q = new SupportTeamOperationalSummaryQuery($wpdb);
        $summary = $q->getTeamSummary(3, ['10']);

        $this->assertSame(3, $summary['counts']['total']);
        $this->assertSame(1, $summary['counts']['team_queue']);
        $this->assertSame(1, $summary['counts']['user_assigned']);
        $this->assertSame(1, $summary['counts']['unrouted']);

        $this->assertSame(1, $summary['sla']['breached']);
        $this->assertSame(1, $summary['sla']['risk']);

        $this->assertSame(1, $summary['unresolved_escalations']['total_open']);
        $this->assertSame(1, $summary['unresolved_escalations']['by_severity']['critical']);
    }
}

