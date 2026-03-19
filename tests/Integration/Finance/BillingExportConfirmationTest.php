<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Finance;

use Pet\Application\Finance\Command\ConfirmBillingExportCommand;
use Pet\Application\Finance\Command\ConfirmBillingExportHandler;
use Pet\Domain\Finance\Entity\BillingExport;
use Pet\Domain\Finance\Entity\BillingExportItem;
use Pet\Infrastructure\Persistence\Repository\SqlBillingExportRepository;
use Pet\Infrastructure\Persistence\Repository\SqlExternalMappingRepository;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;
use Pet\Tests\Integration\Support\WpdbStub;
use Pet\Tests\Stub\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

final class BillingExportConfirmationTest extends TestCase
{
    private WpdbStub $wpdb;
    private SqlBillingExportRepository $exports;
    private SqlExternalMappingRepository $mappings;
    private ConfirmBillingExportHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $this->createSchema();

        $this->exports = new SqlBillingExportRepository($this->wpdb);
        $this->mappings = new SqlExternalMappingRepository($this->wpdb);
        $this->handler = new ConfirmBillingExportHandler(
            new FakeTransactionManager(),
            $this->exports,
            $this->mappings,
            new SqlTransaction($this->wpdb)
        );
    }

    public function testConfirmSentExportRequiresReconciliationEvidenceAndIsIdempotent(): void
    {
        $export = BillingExport::draft(
            'uuid-1',
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
            99
        );
        $this->exports->save($export);

        $item = BillingExportItem::pending($export->id(), 'ticket', 1, 2.0, 50.0, 'Work', null);
        $this->exports->addItem($item);

        $export->queue();
        $this->exports->save($export);

        $export->markSent();
        $this->exports->save($export);

        $this->wpdb->insert($this->wpdb->prefix . 'pet_external_mappings', [
            'system' => 'quickbooks',
            'entity_type' => 'invoice',
            'pet_entity_id' => $export->id(),
            'external_id' => 'qb-inv-123',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $status = $this->handler->handle(new ConfirmBillingExportCommand($export->id()));
        $this->assertSame('confirmed', $status);

        $loaded = $this->exports->findById($export->id());
        $this->assertSame('confirmed', $loaded->status());

        $items = $this->exports->findItems($export->id());
        $this->assertCount(1, $items);
        $this->assertSame('Work', $items[0]->description());
        $this->assertSame(100.0, $items[0]->amount());

        $status2 = $this->handler->handle(new ConfirmBillingExportCommand($export->id()));
        $this->assertSame('confirmed', $status2);
    }

    public function testConfirmRejectsNonSentStates(): void
    {
        $export = BillingExport::draft(
            'uuid-2',
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
            99
        );
        $this->exports->save($export);

        $this->expectException(\DomainException::class);
        $this->handler->handle(new ConfirmBillingExportCommand($export->id()));
    }

    public function testConfirmRejectsSentWithoutEvidence(): void
    {
        $export = BillingExport::draft(
            'uuid-3',
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
            99
        );
        $this->exports->save($export);
        $export->queue();
        $this->exports->save($export);
        $export->markSent();
        $this->exports->save($export);

        $this->expectException(\DomainException::class);
        $this->handler->handle(new ConfirmBillingExportCommand($export->id()));
    }

    private function createSchema(): void
    {
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_billing_exports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL,
                customer_id INTEGER NOT NULL,
                period_start TEXT NOT NULL,
                period_end TEXT NOT NULL,
                status TEXT NOT NULL,
                created_by_employee_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );

        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_billing_export_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                export_id INTEGER NOT NULL,
                source_type TEXT NOT NULL,
                source_id INTEGER NOT NULL,
                quantity REAL NOT NULL,
                unit_price REAL NOT NULL,
                amount REAL NOT NULL,
                description TEXT NOT NULL,
                qb_item_ref TEXT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL
            )"
        );

        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_external_mappings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                system TEXT NOT NULL,
                entity_type TEXT NOT NULL,
                pet_entity_id INTEGER NOT NULL,
                external_id TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );
    }
}

