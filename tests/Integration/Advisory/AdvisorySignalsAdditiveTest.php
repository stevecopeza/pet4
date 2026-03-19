<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Advisory;

use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Infrastructure\Persistence\Repository\SqlAdvisorySignalRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class AdvisorySignalsAdditiveTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $prefix = $this->wpdb->prefix;
        $this->wpdb->query(
            "CREATE TABLE {$prefix}pet_advisory_signals (
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
            )"
        );
    }

    public function testClearForWorkItemDeactivatesAndRetainsHistory(): void
    {
        $repo = new SqlAdvisorySignalRepository($this->wpdb);
        $workItemId = 'wi-1';

        $repo->save(new AdvisorySignal(
            'sig-1',
            $workItemId,
            AdvisorySignal::TYPE_DEADLINE_RISK,
            AdvisorySignal::SEVERITY_WARNING,
            'm1',
            new \DateTimeImmutable('2026-01-01 00:00:00'),
            'ACTIVE',
            null,
            'run-1'
        ));
        $repo->save(new AdvisorySignal(
            'sig-2',
            $workItemId,
            AdvisorySignal::TYPE_SLA_RISK,
            AdvisorySignal::SEVERITY_CRITICAL,
            'm2',
            new \DateTimeImmutable('2026-01-01 00:01:00'),
            'ACTIVE',
            null,
            'run-1'
        ));

        $repo->clearForWorkItem($workItemId, 'run-2');

        $all = $repo->findByWorkItemId($workItemId);
        $this->assertCount(2, $all);
        $this->assertSame('INACTIVE', $all[0]->getStatus());
        $this->assertNotNull($all[0]->getResolvedAt());

        $repo->save(new AdvisorySignal(
            'sig-3',
            $workItemId,
            AdvisorySignal::TYPE_SLA_RISK,
            AdvisorySignal::SEVERITY_WARNING,
            'm3',
            new \DateTimeImmutable('2026-01-01 00:02:00'),
            'ACTIVE',
            null,
            'run-2'
        ));

        $active = $repo->findActiveByWorkItemId($workItemId);
        $this->assertCount(1, $active);
        $this->assertSame('sig-3', $active[0]->getId());
    }
}

