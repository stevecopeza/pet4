<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Delivery\Listener;

use Pet\Application\Delivery\Command\CreateProjectCommand;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Application\Delivery\Listener\CreateProjectFromQuoteListener;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteMilestone;
use Pet\Domain\Commercial\Entity\Component\QuoteTask;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Delivery\ValueObject\ProjectState;
use PHPUnit\Framework\TestCase;

final class CreateProjectFromQuoteListenerTest extends TestCase
{
    public function testInvokingAcceptedQuoteCreatesProjectCommandWithSoldHoursAndNoLegacyTasks(): void
    {
        $quote = $this->buildDeliveryQuote();

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository
            ->expects(self::once())
            ->method('findByQuoteId')
            ->with(777)
            ->willReturn(null);

        $createProjectHandler = $this->getMockBuilder(CreateProjectHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['handle'])
            ->getMock();

        $createProjectHandler
            ->expects(self::once())
            ->method('handle')
            ->with(self::callback(function (CreateProjectCommand $command): bool {
                self::assertSame(44, $command->customerId());
                self::assertSame(777, $command->sourceQuoteId());
                self::assertSame('Quote 777', $command->name());
                self::assertSame([], $command->tasks(), 'Project creation must not carry legacy delivery tasks.');
                self::assertEqualsWithDelta(7.5, $command->soldHours(), 0.00001);
                self::assertEqualsWithDelta(1770.0, $command->soldValue(), 0.00001);
                return true;
            }));

        $listener = new CreateProjectFromQuoteListener($createProjectHandler, $projectRepository);
        $listener(new QuoteAccepted($quote));
    }

    public function testExistingProjectForQuoteSkipsProjectCreation(): void
    {
        $quote = $this->buildDeliveryQuote();

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository
            ->expects(self::once())
            ->method('findByQuoteId')
            ->with(777)
            ->willReturn(new Project(
                44,
                'Existing',
                1.0,
                777,
                ProjectState::planned(),
                100.0,
                null,
                null,
                55
            ));

        $createProjectHandler = $this->getMockBuilder(CreateProjectHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['handle'])
            ->getMock();
        $createProjectHandler->expects(self::never())->method('handle');

        $listener = new CreateProjectFromQuoteListener($createProjectHandler, $projectRepository);
        $listener(new QuoteAccepted($quote));

        self::assertTrue(true);
    }

    private function buildDeliveryQuote(): Quote
    {
        $taskA = new QuoteTask('Discovery', 2.5, 7, 80.0, 120.0, 'Discovery scope', 101);
        $taskB = new QuoteTask('Build', 5.0, 8, 90.0, 130.0, 'Build implementation', 102);
        $milestone = new QuoteMilestone('Implementation Milestone', [$taskA, $taskB], 'Primary milestone', 201);
        $implementation = new ImplementationComponent([$milestone], 'Implementation package', 301);

        $unit = new SimpleUnit('Go-live support', 1.0, 820.0, 600.0, 'Onsite support', 401);
        $onceOff = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_SIMPLE,
            [],
            [$unit],
            'Service package',
            302
        );

        $totalValue = $implementation->sellValue() + $onceOff->sellValue();
        $totalInternal = $implementation->internalCost() + $onceOff->internalCost();

        return new Quote(
            44,
            'Quote 777',
            'Ticket-only delivery quote',
            QuoteState::accepted(),
            5,
            $totalValue,
            $totalInternal,
            'USD',
            new \DateTimeImmutable('2026-03-24 10:00:00'),
            777,
            new \DateTimeImmutable('2026-03-24 09:00:00'),
            null,
            null,
            [$implementation, $onceOff],
            [],
            [],
            [new PaymentMilestone('Full', $totalValue, null, false, 1)],
            11,
            null
        );
    }
}
