<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Support\Service;

use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Service\SlaStateResolver;
use Pet\Domain\Support\ValueObject\SlaState;
use PHPUnit\Framework\TestCase;

class SlaStateResolverTest extends TestCase
{
    private SlaStateResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new SlaStateResolver();
    }

    private function makeTicket(
        string $status = 'open',
        ?\DateTimeImmutable $resolutionDueAt = null,
        ?\DateTimeImmutable $responseDueAt = null,
        ?\DateTimeImmutable $respondedAt = null,
        ?int $id = 1
    ): Ticket {
        return new Ticket(
            customerId: 1,
            subject: 'Test',
            description: 'Test ticket',
            status: $status,
            priority: 'medium',
            id: $id,
            resolutionDueAt: $resolutionDueAt,
            responseDueAt: $responseDueAt,
            respondedAt: $respondedAt
        );
    }

    // ── determineState() ──

    public function testActiveWhenNoSla(): void
    {
        $ticket = $this->makeTicket();
        $now = new \DateTimeImmutable();
        $this->assertSame(SlaState::ACTIVE, $this->resolver->determineState($ticket, $now));
    }

    /** @dataProvider pausedStatusesProvider */
    public function testPausedStatuses(string $status): void
    {
        $ticket = $this->makeTicket(status: $status);
        $now = new \DateTimeImmutable();
        $this->assertSame(SlaState::PAUSED, $this->resolver->determineState($ticket, $now));
    }

    public function pausedStatusesProvider(): array
    {
        return [['pending'], ['on_hold'], ['resolved'], ['closed']];
    }

    public function testBreachedWhenResolutionOverdue(): void
    {
        $due = new \DateTimeImmutable('-2 hours');
        $ticket = $this->makeTicket(resolutionDueAt: $due);
        $now = new \DateTimeImmutable();
        $this->assertSame(SlaState::BREACHED, $this->resolver->determineState($ticket, $now));
    }

    public function testBreachedWhenResponseOverdueAndNotResponded(): void
    {
        $due = new \DateTimeImmutable('-30 minutes');
        $ticket = $this->makeTicket(responseDueAt: $due);
        $now = new \DateTimeImmutable();
        $this->assertSame(SlaState::BREACHED, $this->resolver->determineState($ticket, $now));
    }

    public function testActiveWhenResponseOverdueButAlreadyResponded(): void
    {
        $due = new \DateTimeImmutable('-30 minutes');
        $responded = new \DateTimeImmutable('-1 hour');
        $ticket = $this->makeTicket(responseDueAt: $due, respondedAt: $responded);
        $now = new \DateTimeImmutable();
        // No resolution due, responded already → active
        $this->assertSame(SlaState::ACTIVE, $this->resolver->determineState($ticket, $now));
    }

    public function testWarningWithin1Hour(): void
    {
        $due = new \DateTimeImmutable('+45 minutes');
        $ticket = $this->makeTicket(resolutionDueAt: $due);
        $now = new \DateTimeImmutable();
        $this->assertSame(SlaState::WARNING, $this->resolver->determineState($ticket, $now));
    }

    public function testActiveWhenMoreThan1HourRemaining(): void
    {
        $due = new \DateTimeImmutable('+2 hours');
        $ticket = $this->makeTicket(resolutionDueAt: $due);
        $now = new \DateTimeImmutable();
        $this->assertSame(SlaState::ACTIVE, $this->resolver->determineState($ticket, $now));
    }

    public function testBreachTakesPriorityOverWarning(): void
    {
        // Resolution already overdue — should be BREACHED even though warning threshold passed too
        $due = new \DateTimeImmutable('-5 minutes');
        $ticket = $this->makeTicket(resolutionDueAt: $due);
        $now = new \DateTimeImmutable();
        $this->assertSame(SlaState::BREACHED, $this->resolver->determineState($ticket, $now));
    }

    // ── resolveTransitionEvents() ──

    public function testWarningEventDispatched(): void
    {
        $ticket = $this->makeTicket();
        $clock = new SlaClockState(1);
        $events = $this->resolver->resolveTransitionEvents($ticket, SlaState::WARNING, $clock, false);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(TicketWarningEvent::class, $events[0]);
    }

    public function testBreachEventWithoutEscalation(): void
    {
        $ticket = $this->makeTicket();
        $clock = new SlaClockState(1);
        $events = $this->resolver->resolveTransitionEvents($ticket, SlaState::BREACHED, $clock, false);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(TicketBreachedEvent::class, $events[0]);
    }

    public function testBreachEventWithEscalation(): void
    {
        $ticket = $this->makeTicket();
        $clock = new SlaClockState(1);
        $events = $this->resolver->resolveTransitionEvents($ticket, SlaState::BREACHED, $clock, true);

        $this->assertCount(2, $events);
        $this->assertInstanceOf(TicketBreachedEvent::class, $events[0]);
        $this->assertInstanceOf(EscalationTriggeredEvent::class, $events[1]);
    }

    public function testBreachNoEscalationWhenAlreadyEscalated(): void
    {
        $ticket = $this->makeTicket();
        $clock = new SlaClockState(1);
        $clock->setEscalationStage(1); // already at stage 1
        $events = $this->resolver->resolveTransitionEvents($ticket, SlaState::BREACHED, $clock, true);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(TicketBreachedEvent::class, $events[0]);
    }

    public function testPausedNoEvents(): void
    {
        $ticket = $this->makeTicket();
        $clock = new SlaClockState(1);
        $events = $this->resolver->resolveTransitionEvents($ticket, SlaState::PAUSED, $clock, true);

        $this->assertEmpty($events);
    }

    public function testActiveNoEvents(): void
    {
        $ticket = $this->makeTicket();
        $clock = new SlaClockState(1);
        $events = $this->resolver->resolveTransitionEvents($ticket, SlaState::ACTIVE, $clock, true);

        $this->assertEmpty($events);
    }
}
