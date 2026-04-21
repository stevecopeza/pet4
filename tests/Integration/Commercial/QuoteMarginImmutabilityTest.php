<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Commercial;

use Pet\Application\Commercial\Command\CreateQuoteBlockCommand;
use Pet\Application\Commercial\Command\CreateQuoteBlockHandler;
use Pet\Application\Commercial\Command\UpdateQuoteBlockCommand;
use Pet\Application\Commercial\Command\UpdateQuoteBlockHandler;
use Pet\Application\Commercial\Service\QuoteBlockCostSnapshotEnricher;
use Pet\Application\Commercial\Service\QuoteBlockMarginCalculator;
use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Infrastructure\Persistence\Repository\SqlCatalogItemRepository;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteBlockRepository;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteSectionRepository;
use Pet\Infrastructure\Persistence\Repository\SqlRoleRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use Pet\Tests\Stub\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

final class QuoteMarginImmutabilityTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new class extends WpdbStub {
            public function insert(string $table, array $data, ...$formats)
            {
                return parent::insert($table, $data);
            }

            public function update(string $table, array $data, array $where, ...$formats)
            {
                return parent::update($table, $data, $where);
            }

            public function delete(string $table, array $where, ...$formats)
            {
                $this->last_error = '';

                if (empty($where)) {
                    $this->last_error = 'Delete requires a non-empty where clause.';
                    return false;
                }

                $conditions = [];
                $values = [];
                foreach ($where as $column => $value) {
                    if ($value === null) {
                        $conditions[] = "{$column} IS NULL";
                        continue;
                    }

                    $conditions[] = "{$column} = ?";
                    $values[] = $value;
                }

                $sql = sprintf('DELETE FROM %s WHERE %s', $table, implode(' AND ', $conditions));

                try {
                    $stmt = $this->getPdo()->prepare($sql);
                    $stmt->execute($values);
                    return $stmt->rowCount();
                } catch (\PDOException $e) {
                    $this->last_error = $e->getMessage();
                    return false;
                }
            }
        };

        $this->createSchema();
    }

    public function testAcceptedQuoteMarginsRemainImmutableAfterSourceRoleRateChanges(): void
    {
        $quoteRepository = new SqlQuoteRepository($this->wpdb);
        $quoteBlockRepository = new SqlQuoteBlockRepository($this->wpdb);
        $quoteSectionRepository = new SqlQuoteSectionRepository($this->wpdb);
        $roleRepository = new SqlRoleRepository($this->wpdb);
        $catalogItemRepository = new SqlCatalogItemRepository($this->wpdb);
        $enricher = new QuoteBlockCostSnapshotEnricher($catalogItemRepository, $roleRepository);
        $calculator = new QuoteBlockMarginCalculator();

        $createHandler = new CreateQuoteBlockHandler(
            new FakeTransactionManager(),
            $quoteRepository,
            $quoteSectionRepository,
            $quoteBlockRepository,
            $enricher
        );
        $updateHandler = new UpdateQuoteBlockHandler(
            new FakeTransactionManager(),
            $quoteRepository,
            $quoteBlockRepository,
            $enricher
        );

        $roleId = $this->createRole(80.0);

        $quote = new Quote(
            1001,
            'Margin Immutability',
            'Role-backed quote line',
            QuoteState::draft()
        );
        $quoteRepository->save($quote);
        $quoteId = (int) $quote->id();
        self::assertGreaterThan(0, $quoteId);

        $createdBlock = $createHandler->handle(new CreateQuoteBlockCommand(
            $quoteId,
            null,
            QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE,
            [
                'description' => 'Role-backed service',
                'quantity' => 2,
                'sellValue' => 150.0,
                'totalValue' => 300.0,
                'roleId' => $roleId,
            ]
        ));
        $blockId = (int) $createdBlock->id();

        $initialBlock = $this->findBlockById($quoteBlockRepository, $quoteId, $blockId);
        $initialMetrics = $calculator->calculate($initialBlock->type(), $initialBlock->payload());

        self::assertSame(160.0, $initialMetrics['lineCostValue']);
        self::assertSame(140.0, $initialMetrics['marginAmount']);
        self::assertNotNull($initialMetrics['marginPercentage']);

        $prefix = $this->wpdb->prefix;
        $acceptedRows = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$prefix}pet_quotes SET state = %s, accepted_at = %s WHERE id = %d",
            QuoteState::ACCEPTED,
            '2026-01-31 10:00:00',
            $quoteId
        ));
        self::assertSame(1, $acceptedRows);

        $sourceUpdatedRows = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$prefix}pet_roles SET base_internal_rate = %f WHERE id = %d",
            220.0,
            $roleId
        ));
        self::assertSame(1, $sourceUpdatedRows);

        try {
            $updateHandler->handle(new UpdateQuoteBlockCommand(
                $quoteId,
                $blockId,
                [
                    'description' => 'Role-backed service',
                    'quantity' => 2,
                    'sellValue' => 150.0,
                    'totalValue' => 300.0,
                    'roleId' => $roleId,
                ]
            ));
            self::fail('Expected update on accepted quote to be rejected.');
        } catch (\DomainException $exception) {
            self::assertSame('Cannot update blocks on an accepted quote.', $exception->getMessage());
        }

        $afterSourceChangeBlock = $this->findBlockById($quoteBlockRepository, $quoteId, $blockId);
        $afterSourceChangeMetrics = $calculator->calculate(
            $afterSourceChangeBlock->type(),
            $afterSourceChangeBlock->payload()
        );

        self::assertSame($initialMetrics['marginAmount'], $afterSourceChangeMetrics['marginAmount']);
        self::assertEqualsWithDelta(
            (float) $initialMetrics['marginPercentage'],
            (float) $afterSourceChangeMetrics['marginPercentage'],
            0.000001
        );
        self::assertSame($initialMetrics['lineCostValue'], $afterSourceChangeMetrics['lineCostValue']);
        self::assertSame(
            $initialBlock->payload()['totalCost'] ?? null,
            $afterSourceChangeBlock->payload()['totalCost'] ?? null
        );
    }

    private function createSchema(): void
    {
        $p = $this->wpdb->prefix;

        $this->wpdb->query(
            "CREATE TABLE {$p}pet_quotes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                lead_id INTEGER NULL,
                contract_id INTEGER NULL,
                title TEXT NOT NULL,
                description TEXT NULL,
                state TEXT NOT NULL,
                rejection_note TEXT NULL,
                submitted_for_approval_at TEXT NULL,
                approved_at TEXT NULL,
                approved_by_user_id INTEGER NULL,
                version INTEGER NOT NULL,
                total_value REAL NOT NULL,
                total_internal_cost REAL NOT NULL,
                currency TEXT NULL,
                accepted_at TEXT NULL,
                malleable_data TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                archived_at TEXT NULL
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$p}pet_quote_components (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quote_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                section TEXT NULL,
                description TEXT NULL
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$p}pet_quote_payment_schedule (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quote_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                amount REAL NOT NULL,
                due_date TEXT NULL,
                paid_flag INTEGER NOT NULL DEFAULT 0
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$p}pet_cost_adjustments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quote_id INTEGER NOT NULL,
                description TEXT NOT NULL,
                amount REAL NOT NULL,
                reason TEXT NOT NULL,
                approved_by TEXT NOT NULL,
                applied_at TEXT NOT NULL
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$p}pet_quote_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quote_id INTEGER NOT NULL,
                component_id INTEGER NULL,
                section_id INTEGER NULL,
                type TEXT NOT NULL,
                order_index INTEGER NOT NULL,
                priced INTEGER NOT NULL DEFAULT 1,
                payload_json TEXT NULL
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$p}pet_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL,
                level TEXT NOT NULL,
                description TEXT NOT NULL,
                success_criteria TEXT NOT NULL,
                base_internal_rate REAL NULL,
                created_at TEXT NOT NULL,
                published_at TEXT NULL
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$p}pet_role_skills (
                role_id INTEGER NOT NULL,
                skill_id INTEGER NOT NULL,
                min_proficiency_level INTEGER NOT NULL,
                importance_weight INTEGER NOT NULL
            )"
        );
    }

    private function createRole(float $baseInternalRate): int
    {
        $p = $this->wpdb->prefix;

        $this->wpdb->insert($p . 'pet_roles', [
            'name' => 'Engineer',
            'version' => 1,
            'status' => 'draft',
            'level' => 'L2',
            'description' => 'Delivery engineer',
            'success_criteria' => 'Successful implementation',
            'base_internal_rate' => $baseInternalRate,
            'created_at' => '2026-01-01 00:00:00',
            'published_at' => null,
        ]);

        return (int) $this->wpdb->insert_id;
    }

    private function findBlockById(SqlQuoteBlockRepository $repository, int $quoteId, int $blockId): QuoteBlock
    {
        $blocks = $repository->findByQuoteId($quoteId);
        foreach ($blocks as $block) {
            if ($block->id() === $blockId) {
                return $block;
            }
        }

        self::fail("Unable to find block {$blockId} for quote {$quoteId}.");
    }
}
