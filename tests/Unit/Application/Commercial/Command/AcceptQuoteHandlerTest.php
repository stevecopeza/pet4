<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\CreateProjectTicketCommand;
use Pet\Application\Commercial\Command\CreateProjectTicketHandler;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Tests\Stub\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

final class AcceptQuoteHandlerTest extends TestCase
{
    public function testConstructorRejectsNullCreateProjectTicketHandler(): void
    {
        $quoteRepository = $this->createMock(QuoteRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        $this->expectException(\TypeError::class);

        new AcceptQuoteHandler(
            new FakeTransactionManager(),
            $quoteRepository,
            $eventBus,
            null
        );
    }

    public function testHandleAcceptsQuoteAndProvisionsTicketsWhenDependencyIsPresent(): void
    {
        $quote = $this->buildDeliveryQuote(
            QuoteState::SENT,
            6001,
            7001
        );
        $persistedQuote = $this->buildDeliveryQuote(
            QuoteState::ACCEPTED,
            6002,
            7002
        );
        $project = new Project(
            42,
            'Project for Quote #321',
            25.0,
            321,
            null,
            1500.0,
            null,
            null,
            99
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->expects(self::exactly(2))
            ->method('findById')
            ->with(321, true)
            ->willReturnOnConsecutiveCalls($quote, $persistedQuote);
        $quoteRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Quote $saved): bool {
                return $saved->state()->toString() === QuoteState::ACCEPTED;
            }));

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::once())
            ->method('findByQuoteId')
            ->with(321)
            ->willReturn($project);

        $createProjectTicketHandler = $this->createMock(CreateProjectTicketHandler::class);
        $createProjectTicketHandler->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (CreateProjectTicketCommand $command): bool {
                return $command->sourceComponentId() === 6002;
            }))
            ->willReturn(1001);

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::atLeastOnce())
            ->method('dispatch');

        $handler = new AcceptQuoteHandler(
            new FakeTransactionManager(),
            $quoteRepository,
            $eventBus,
            $createProjectTicketHandler,
            null,
            null,
            $projectRepository,
            null,
            null
        );

        $handler->handle(new AcceptQuoteCommand(321));

        self::assertSame(QuoteState::ACCEPTED, $quote->state()->toString());
        self::assertNotNull($quote->acceptedAt());
    }
    private function buildDeliveryQuote(string $state, int $componentId, int $unitId): Quote
    {
        $unit = new SimpleUnit(
            'Initial Delivery Unit',
            1.0,
            1500.0,
            900.0,
            'Single once-off service unit',
            $unitId
        );
        $component = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_SIMPLE,
            [],
            [$unit],
            'Delivery Component',
            $componentId,
            'General'
        );
        $milestone = new PaymentMilestone(
            'Initial Payment',
            1500.0,
            new \DateTimeImmutable('+7 days'),
            false,
            1
        );

        return new Quote(
            42,
            'Delivery Quote',
            'Quote used for mandatory provisioning acceptance test',
            QuoteState::fromString($state),
            1,
            1500.0,
            900.0,
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
}
