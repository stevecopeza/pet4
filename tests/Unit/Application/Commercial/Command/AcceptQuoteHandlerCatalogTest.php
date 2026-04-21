<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\CreateProjectTicketCommand;
use Pet\Application\Commercial\Command\CreateProjectTicketHandler;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Tests\Stub\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CatalogComponent ticket provisioning on quote acceptance.
 *
 * Covers:
 *   - Single catalog item   → one ticket, no rollup
 *   - Multiple catalog items → rollup + N children
 *   - Product type label    → "Fulfil: N× SKU"
 *   - Service type label    → "Deliver service: N× SKU"
 *   - roleId propagated for service items
 *   - Fractional quantity formatted without trailing zeros
 *   - Empty items list      → no tickets created
 *   - Missing component id  → RuntimeException
 *   - Missing item id       → RuntimeException (multi-item path)
 */
final class AcceptQuoteHandlerCatalogTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Happy-path: single service item
    // -----------------------------------------------------------------------

    public function testSingleServiceItemCreatesOneTicketWithCorrectSubject(): void
    {
        $item = $this->makeItem(id: 501, type: 'service', sku: 'CONSULT-HR', qty: 2.0, unitPrice: 500.0, roleId: 7);
        $component = new CatalogComponent([$item], 'HR Consulting', 301);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $handler = $this->buildHandler(
            $persistedQuote,
            expectedCalls: 1,
            callbackAssertions: [
                function (CreateProjectTicketCommand $cmd): bool {
                    self::assertSame('Deliver service: 2× CONSULT-HR', $cmd->subject());
                    self::assertSame(301, $cmd->sourceComponentId()); // component id, not item id
                    self::assertNull($cmd->parentTicketId());
                    self::assertFalse($cmd->isRollup());
                    self::assertSame(7, $cmd->requiredRoleId());
                    self::assertSame(100000, $cmd->soldValueCents()); // 2 × 500 × 100
                    return true;
                },
            ],
            returnValues: [1001]
        );

        $handler->handle(new AcceptQuoteCommand(321));
    }

    public function testSingleProductItemCreatesOneTicketWithCorrectSubject(): void
    {
        $item = $this->makeItem(id: 502, type: 'product', sku: 'LAPTOP-PRO', qty: 3.0, unitPrice: 1200.0, roleId: null);
        $component = new CatalogComponent([$item], 'Laptops', 302);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $handler = $this->buildHandler(
            $persistedQuote,
            expectedCalls: 1,
            callbackAssertions: [
                function (CreateProjectTicketCommand $cmd): bool {
                    self::assertSame('Fulfil: 3× LAPTOP-PRO', $cmd->subject());
                    self::assertNull($cmd->requiredRoleId());
                    self::assertSame(360000, $cmd->soldValueCents()); // 3 × 1200 × 100
                    return true;
                },
            ],
            returnValues: [1002]
        );

        $handler->handle(new AcceptQuoteCommand(321));
    }

    // -----------------------------------------------------------------------
    // Happy-path: multiple items — rollup + children
    // -----------------------------------------------------------------------

    public function testMultipleItemsCreatesRollupAndChildren(): void
    {
        $item1 = $this->makeItem(id: 601, type: 'service', sku: 'SVC-A', qty: 1.0, unitPrice: 800.0, roleId: 3);
        $item2 = $this->makeItem(id: 602, type: 'product', sku: 'SWITCH-48', qty: 2.0, unitPrice: 450.0, roleId: null);
        $component = new CatalogComponent([$item1, $item2], 'Network Bundle', 401);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $capturedCommands = [];
        $handler = $this->buildHandlerCapturing($persistedQuote, $capturedCommands, [9001, 9002, 9003]);

        $handler->handle(new AcceptQuoteCommand(321));

        self::assertCount(3, $capturedCommands);

        /** @var CreateProjectTicketCommand $rollup */
        $rollup = $capturedCommands[0];
        self::assertTrue($rollup->isRollup());
        self::assertSame('Network Bundle', $rollup->subject());
        self::assertSame(401, $rollup->sourceComponentId());
        self::assertNull($rollup->parentTicketId());
        self::assertSame(170000, $rollup->soldValueCents()); // 800 + 900 = 1700 → cents

        /** @var CreateProjectTicketCommand $child1 */
        $child1 = $capturedCommands[1];
        self::assertFalse($child1->isRollup());
        self::assertSame('Deliver service: 1× SVC-A', $child1->subject());
        self::assertSame(601, $child1->sourceComponentId());
        self::assertSame(9001, $child1->parentTicketId());
        self::assertSame(3, $child1->requiredRoleId());

        /** @var CreateProjectTicketCommand $child2 */
        $child2 = $capturedCommands[2];
        self::assertFalse($child2->isRollup());
        self::assertSame('Fulfil: 2× SWITCH-48', $child2->subject());
        self::assertSame(602, $child2->sourceComponentId());
        self::assertSame(9001, $child2->parentTicketId());
        self::assertNull($child2->requiredRoleId());
    }

    // -----------------------------------------------------------------------
    // Rollup description falls back to generated label when description empty
    // -----------------------------------------------------------------------

    public function testRollupSubjectFallsBackToGeneratedLabel(): void
    {
        $item1 = $this->makeItem(id: 701, type: 'service', sku: 'SVC-X', qty: 1.0, unitPrice: 100.0, roleId: 5);
        $item2 = $this->makeItem(id: 702, type: 'service', sku: 'SVC-Y', qty: 1.0, unitPrice: 200.0, roleId: 6);
        $component = new CatalogComponent([$item1, $item2], '', 501); // empty description

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);
        $capturedCommands = [];
        $this->buildHandlerCapturing($persistedQuote, $capturedCommands, [8001, 8002, 8003])
             ->handle(new AcceptQuoteCommand(321));

        self::assertSame('Catalog Component #501', $capturedCommands[0]->subject());
    }

    // -----------------------------------------------------------------------
    // Quantity formatting — fractional
    // -----------------------------------------------------------------------

    public function testFractionalQuantityFormattedCorrectly(): void
    {
        $item = $this->makeItem(id: 801, type: 'service', sku: 'PART-TIME', qty: 0.5, unitPrice: 1000.0, roleId: 4);
        $component = new CatalogComponent([$item], 'Part-time support', 601);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);
        $capturedCommands = [];
        $this->buildHandlerCapturing($persistedQuote, $capturedCommands, [7001])
             ->handle(new AcceptQuoteCommand(321));

        self::assertSame('Deliver service: 0.5× PART-TIME', $capturedCommands[0]->subject());
    }

    // -----------------------------------------------------------------------
    // Empty items list — no tickets created
    // -----------------------------------------------------------------------

    public function testEmptyItemsListCreatesNoTickets(): void
    {
        $component = new CatalogComponent([], 'Empty Catalog', 801);
        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $createTicketHandler = $this->createMock(CreateProjectTicketHandler::class);
        $createTicketHandler->expects(self::never())->method('handle');

        $handler = $this->makeHandlerWithTicketHandler($persistedQuote, $createTicketHandler);
        $handler->handle(new AcceptQuoteCommand(321));
    }

    // -----------------------------------------------------------------------
    // Guard: missing component id
    // -----------------------------------------------------------------------

    public function testMissingComponentIdThrows(): void
    {
        // id = null → (int)null = 0 → <= 0 → exception
        $item = $this->makeItem(id: 1001, type: 'service', sku: 'SVC', qty: 1.0, unitPrice: 100.0, roleId: 2);
        $component = new CatalogComponent([$item], 'Test', null); // null id

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $handler = $this->makeHandlerWithTicketHandler(
            $persistedQuote,
            $this->createMock(CreateProjectTicketHandler::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/catalog component missing persistent id/');

        $handler->handle(new AcceptQuoteCommand(321));
    }

    // -----------------------------------------------------------------------
    // WBS splitting — service item with wbsSnapshot
    // -----------------------------------------------------------------------

    public function testSingleServiceItemWithWbsSnapshotCreatesRollupAndChildren(): void
    {
        $wbs = [
            ['description' => 'Discovery workshop', 'hours' => 4.0],
            ['description' => 'Development',        'hours' => 16.0],
            ['description' => 'UAT & sign-off',     'hours' => 2.0],
        ];
        $item = $this->makeItem(id: 551, type: 'service', sku: 'IMPL-PKG', qty: 1.0, unitPrice: 5000.0, roleId: 9, wbsSnapshot: $wbs);
        $component = new CatalogComponent([$item], 'Implementation Package', 311);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $capturedCommands = [];
        $this->buildHandlerCapturing($persistedQuote, $capturedCommands, [2001, 2002, 2003, 2004])
             ->handle(new AcceptQuoteCommand(321));

        // 1 rollup + 3 WBS children = 4 tickets total.
        self::assertCount(4, $capturedCommands);

        /** @var CreateProjectTicketCommand $rollup */
        $rollup = $capturedCommands[0];
        self::assertTrue($rollup->isRollup());
        self::assertSame('Deliver service: 1× IMPL-PKG', $rollup->subject());
        self::assertSame(311, $rollup->sourceComponentId()); // component id for single item
        self::assertNull($rollup->parentTicketId());
        self::assertSame(500000, $rollup->soldValueCents()); // 1 × 5000 × 100

        /** @var CreateProjectTicketCommand $task1 */
        $task1 = $capturedCommands[1];
        self::assertFalse($task1->isRollup());
        self::assertSame('Discovery workshop', $task1->subject());
        self::assertSame(240, $task1->estimatedMinutes()); // 4h × 60
        self::assertSame(2001, $task1->parentTicketId());
        self::assertSame(551 * 10000 + 0, $task1->sourceComponentId()); // itemId*10000+taskIndex
        self::assertSame(9, $task1->requiredRoleId());

        /** @var CreateProjectTicketCommand $task2 */
        $task2 = $capturedCommands[2];
        self::assertSame('Development', $task2->subject());
        self::assertSame(960, $task2->estimatedMinutes()); // 16h × 60
        self::assertSame(551 * 10000 + 1, $task2->sourceComponentId());

        /** @var CreateProjectTicketCommand $task3 */
        $task3 = $capturedCommands[3];
        self::assertSame('UAT & sign-off', $task3->subject());
        self::assertSame(120, $task3->estimatedMinutes()); // 2h × 60
        self::assertSame(551 * 10000 + 2, $task3->sourceComponentId());
    }

    public function testWbsTaskWithEmptyDescriptionFallsBackToTaskN(): void
    {
        $wbs = [['description' => '', 'hours' => 3.0]];
        $item = $this->makeItem(id: 552, type: 'service', sku: 'SVC-X', qty: 1.0, unitPrice: 300.0, roleId: 11, wbsSnapshot: $wbs);
        $component = new CatalogComponent([$item], 'X Service', 312);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $capturedCommands = [];
        $this->buildHandlerCapturing($persistedQuote, $capturedCommands, [3001, 3002])
             ->handle(new AcceptQuoteCommand(321));

        self::assertCount(2, $capturedCommands);
        self::assertSame('Task 1', $capturedCommands[1]->subject());
    }

    /**
     * Product items with a WBS snapshot are rejected at the Quote domain level
     * (Quote::validateInvariantsForCurrentState throws DomainException).
     * Therefore no AcceptQuoteHandler test is needed for this case — the domain
     * invariant ensures it never reaches the handler.
     *
     * @see Quote::validateInvariantsForCurrentState (line ~304)
     */
    public function testProductItemWithoutWbsCreatesFlatTicket(): void
    {
        // Products have no WBS — they create one flat fulfilment ticket.
        $item = $this->makeItem(id: 553, type: 'product', sku: 'SWITCH-24', qty: 1.0, unitPrice: 600.0, roleId: null);
        $component = new CatalogComponent([$item], 'Switch', 313);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $capturedCommands = [];
        $this->buildHandlerCapturing($persistedQuote, $capturedCommands, [4001])
             ->handle(new AcceptQuoteCommand(321));

        self::assertCount(1, $capturedCommands);
        self::assertFalse($capturedCommands[0]->isRollup());
        self::assertSame('Fulfil: 1× SWITCH-24', $capturedCommands[0]->subject());
        self::assertSame(0, $capturedCommands[0]->estimatedMinutes()); // products have no WBS estimate
    }

    public function testMultiItemComponentWhereOneHasWbs(): void
    {
        $wbs = [
            ['description' => 'Setup',  'hours' => 2.0],
            ['description' => 'Config', 'hours' => 1.5],
        ];
        $serviceItem  = $this->makeItem(id: 561, type: 'service', sku: 'SETUP-SVC', qty: 1.0, unitPrice: 400.0, roleId: 5, wbsSnapshot: $wbs);
        $productItem  = $this->makeItem(id: 562, type: 'product', sku: 'CABLE-CAT6', qty: 4.0, unitPrice: 20.0, roleId: null);
        $component    = new CatalogComponent([$serviceItem, $productItem], 'Network Setup Bundle', 321);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $capturedCommands = [];
        // component rollup (1) + service rollup (1) + 2 WBS children (2) + product flat (1) = 5 total
        $this->buildHandlerCapturing($persistedQuote, $capturedCommands, [5001, 5002, 5003, 5004, 5005])
             ->handle(new AcceptQuoteCommand(321));

        self::assertCount(5, $capturedCommands);

        $componentRollup = $capturedCommands[0];
        self::assertTrue($componentRollup->isRollup());
        self::assertSame('Network Setup Bundle', $componentRollup->subject());
        self::assertSame(321, $componentRollup->sourceComponentId());
        self::assertNull($componentRollup->parentTicketId());

        $serviceRollup = $capturedCommands[1];
        self::assertTrue($serviceRollup->isRollup());
        self::assertSame('Deliver service: 1× SETUP-SVC', $serviceRollup->subject());
        self::assertSame(5001, $serviceRollup->parentTicketId());
        self::assertSame(561, $serviceRollup->sourceComponentId()); // item id

        $wbsTask1 = $capturedCommands[2];
        self::assertFalse($wbsTask1->isRollup());
        self::assertSame('Setup', $wbsTask1->subject());
        self::assertSame(5002, $wbsTask1->parentTicketId()); // parent = service rollup

        $wbsTask2 = $capturedCommands[3];
        self::assertSame('Config', $wbsTask2->subject());
        self::assertSame(5002, $wbsTask2->parentTicketId());

        $productFlat = $capturedCommands[4];
        self::assertFalse($productFlat->isRollup());
        self::assertSame('Fulfil: 4× CABLE-CAT6', $productFlat->subject());
        self::assertSame(562, $productFlat->sourceComponentId());
        self::assertSame(5001, $productFlat->parentTicketId()); // parent = component rollup
    }

    // -----------------------------------------------------------------------
    // Guard: missing item id in multi-item path
    // -----------------------------------------------------------------------

    public function testMissingItemIdThrowsInMultiItemPath(): void
    {
        $item1 = $this->makeItem(id: 1101, type: 'service', sku: 'SVC-1', qty: 1.0, unitPrice: 100.0, roleId: 2);
        $item2 = $this->makeItem(id: null, type: 'service', sku: 'SVC-2', qty: 1.0, unitPrice: 100.0, roleId: 3); // null id
        $component = new CatalogComponent([$item1, $item2], 'Test', 901);

        $persistedQuote = $this->buildQuoteWithCatalog(QuoteState::ACCEPTED, $component);

        $createTicketHandler = $this->createMock(CreateProjectTicketHandler::class);
        $createTicketHandler->method('handle')->willReturn(5000); // rollup succeeds

        $handler = $this->makeHandlerWithTicketHandler($persistedQuote, $createTicketHandler);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/catalog item missing persistent id at index/');

        $handler->handle(new AcceptQuoteCommand(321));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeItem(
        ?int $id,
        string $type,
        ?string $sku,
        float $qty,
        float $unitPrice,
        ?int $roleId = null,
        array $wbsSnapshot = []
    ): QuoteCatalogItem {
        $description = $sku !== null ? $sku . ' item' : 'Widget item';
        return new QuoteCatalogItem(
            $description,
            $qty,
            $unitPrice,
            $unitPrice * 0.6,
            $id,
            null,
            $wbsSnapshot,
            $type,
            $sku,
            $roleId
        );
    }

    private function buildQuoteWithCatalog(string $state, CatalogComponent $component): Quote
    {
        $milestone = new PaymentMilestone(
            'On acceptance',
            $component->sellValue(),
            new \DateTimeImmutable('+30 days'),
            false,
            1
        );

        return new Quote(
            42,
            'Catalog Quote',
            'Quote for catalog provisioning test',
            QuoteState::fromString($state),
            1,
            $component->sellValue(),
            $component->internalCost(),
            'USD',
            $state === QuoteState::ACCEPTED ? new \DateTimeImmutable() : null,
            321,
            new \DateTimeImmutable('-1 day'),
            null,
            null,
            [$component],
            [],
            [],
            [$milestone]
        );
    }

    /**
     * Build a handler wired with a mock that asserts each call using the given callbacks.
     *
     * @param list<callable(CreateProjectTicketCommand): bool> $callbackAssertions
     * @param list<int> $returnValues
     */
    private function buildHandler(
        Quote $persistedQuote,
        int $expectedCalls,
        array $callbackAssertions,
        array $returnValues
    ): AcceptQuoteHandler {
        $createTicketHandler = $this->createMock(CreateProjectTicketHandler::class);

        $invocation = $createTicketHandler->expects(self::exactly($expectedCalls))->method('handle');
        foreach ($callbackAssertions as $cb) {
            $invocation->with(self::callback($cb));
        }
        $invocation->willReturnOnConsecutiveCalls(...$returnValues);

        return $this->makeHandlerWithTicketHandler($persistedQuote, $createTicketHandler);
    }

    /**
     * Build a handler that captures all CreateProjectTicketCommand objects passed to it.
     *
     * @param CreateProjectTicketCommand[] $captured (by-ref output)
     * @param list<int> $returnValues
     */
    private function buildHandlerCapturing(
        Quote $persistedQuote,
        array &$captured,
        array $returnValues
    ): AcceptQuoteHandler {
        $capturedRef = &$captured;
        $idx = 0;

        $createTicketHandler = $this->createMock(CreateProjectTicketHandler::class);
        $createTicketHandler->method('handle')->willReturnCallback(
            function (CreateProjectTicketCommand $cmd) use (&$capturedRef, &$idx, $returnValues): int {
                $capturedRef[] = $cmd;
                return $returnValues[$idx++] ?? 0;
            }
        );

        return $this->makeHandlerWithTicketHandler($persistedQuote, $createTicketHandler);
    }

    private function makeHandlerWithTicketHandler(
        Quote $persistedQuote,
        CreateProjectTicketHandler $createTicketHandler
    ): AcceptQuoteHandler {
        $quoteId = (int)$persistedQuote->id();

        $sentQuote = $this->buildQuoteWithCatalog(
            QuoteState::SENT,
            // Re-use the same component type; the handler re-fetches after save.
            $persistedQuote->components()[0] instanceof CatalogComponent
                ? $persistedQuote->components()[0]
                : new CatalogComponent([], null, 1)
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->method('findById')
            ->with($quoteId, true)
            ->willReturnOnConsecutiveCalls($sentQuote, $persistedQuote);
        $quoteRepository->method('save');

        $project = new Project(42, 'Project #' . $quoteId, 25.0, $quoteId, null, 0.0, null, null, 99);
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('findByQuoteId')->with($quoteId)->willReturn($project);

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->method('dispatch');

        return new AcceptQuoteHandler(
            new FakeTransactionManager(),
            $quoteRepository,
            $eventBus,
            $createTicketHandler,
            null,
            null,
            $projectRepository,
            null,
            null
        );
    }
}
