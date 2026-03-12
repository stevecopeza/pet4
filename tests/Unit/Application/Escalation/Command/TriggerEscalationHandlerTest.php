<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Escalation\Command;

use Pet\Application\Escalation\Command\TriggerEscalationCommand;
use Pet\Application\Escalation\Command\TriggerEscalationHandler;
use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Event\EscalationTriggeredEvent;
use Pet\Tests\Stub\FakeTransactionManager;
use Pet\Tests\Stub\InMemoryEscalationRepository;
use Pet\Tests\Stub\SpyEventBus;
use PHPUnit\Framework\TestCase;

class TriggerEscalationHandlerTest extends TestCase
{
    private InMemoryEscalationRepository $repo;
    private SpyEventBus $eventBus;
    private TriggerEscalationHandler $handler;

    protected function setUp(): void
    {
        $this->repo = new InMemoryEscalationRepository();
        $this->eventBus = new SpyEventBus();
        $this->handler = new TriggerEscalationHandler(
            new FakeTransactionManager(),
            $this->repo,
            $this->eventBus
        );
    }

    private function makeCommand(): TriggerEscalationCommand
    {
        return new TriggerEscalationCommand(
            'ticket',
            42,
            Escalation::SEVERITY_HIGH,
            'SLA breach – escalation stage 1',
            1,
            ['origin' => 'sla_breach']
        );
    }

    // ── A. Idempotency ──

    public function testFirstTriggerCreatesEscalation(): void
    {
        $id = $this->handler->handle($this->makeCommand());

        $this->assertGreaterThan(0, $id);
        $this->assertCount(1, $this->repo->all());
        $this->assertSame(Escalation::STATUS_OPEN, $this->repo->findById($id)->status());
    }

    public function testRepeatedTriggerReturnsSameId(): void
    {
        $id1 = $this->handler->handle($this->makeCommand());
        $id2 = $this->handler->handle($this->makeCommand());

        $this->assertSame($id1, $id2);
        $this->assertCount(1, $this->repo->all(), 'Only one escalation should exist');
    }

    public function testRepeatedTriggerDoesNotDispatchEventTwice(): void
    {
        $this->handler->handle($this->makeCommand());
        $this->handler->handle($this->makeCommand());

        $triggered = $this->eventBus->dispatchedOfType(EscalationTriggeredEvent::class);
        $this->assertCount(1, $triggered, 'Event should fire only on first trigger');
    }

    // ── B. Concurrent / retry safety ──

    public function testDifferentSeverityCreatesSeparateEscalation(): void
    {
        $cmd1 = new TriggerEscalationCommand('ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach');
        $cmd2 = new TriggerEscalationCommand('ticket', 42, Escalation::SEVERITY_CRITICAL, 'SLA breach');

        $id1 = $this->handler->handle($cmd1);
        $id2 = $this->handler->handle($cmd2);

        $this->assertNotSame($id1, $id2);
        $this->assertCount(2, $this->repo->all());
    }

    public function testDifferentEntityCreatesSeparateEscalation(): void
    {
        $cmd1 = new TriggerEscalationCommand('ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach');
        $cmd2 = new TriggerEscalationCommand('ticket', 99, Escalation::SEVERITY_HIGH, 'SLA breach');

        $id1 = $this->handler->handle($cmd1);
        $id2 = $this->handler->handle($cmd2);

        $this->assertNotSame($id1, $id2);
    }

    // ── C. Initial transition persistence ──

    public function testCreationWritesInitialOpenTransition(): void
    {
        $id = $this->handler->handle($this->makeCommand());

        $transitions = $this->repo->findTransitionsByEscalationId($id);
        $this->assertCount(1, $transitions);
        $this->assertNull($transitions[0]->fromStatus());
        $this->assertSame(Escalation::STATUS_OPEN, $transitions[0]->toStatus());
        $this->assertSame($id, $transitions[0]->escalationId());
    }

    public function testRepeatedTriggerDoesNotDuplicateTransition(): void
    {
        $id = $this->handler->handle($this->makeCommand());
        $this->handler->handle($this->makeCommand());

        $transitions = $this->repo->findTransitionsByEscalationId($id);
        $this->assertCount(1, $transitions, 'Only one initial transition should exist');
    }

    // ── Dedupe key is set ──

    public function testNewEscalationHasDedupeKey(): void
    {
        $id = $this->handler->handle($this->makeCommand());
        $esc = $this->repo->findById($id);

        $this->assertNotNull($esc->openDedupeKey());
        $this->assertSame(64, strlen($esc->openDedupeKey()), 'SHA-256 hex produces 64 chars');
    }
}
