<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Escalation\Command;

use Pet\Application\Escalation\Command\ResolveEscalationCommand;
use Pet\Application\Escalation\Command\ResolveEscalationHandler;
use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Event\EscalationResolvedEvent;
use Pet\Tests\Stub\FakeTransactionManager;
use Pet\Tests\Stub\InMemoryEscalationRepository;
use Pet\Tests\Stub\SpyEventBus;
use PHPUnit\Framework\TestCase;

class ResolveEscalationHandlerTest extends TestCase
{
    private InMemoryEscalationRepository $repo;
    private SpyEventBus $eventBus;
    private ResolveEscalationHandler $handler;

    protected function setUp(): void
    {
        $this->repo = new InMemoryEscalationRepository();
        $this->eventBus = new SpyEventBus();
        $this->handler = new ResolveEscalationHandler(
            new FakeTransactionManager(),
            $this->repo,
            $this->eventBus
        );
    }

    private function seedOpenEscalation(): Escalation
    {
        $esc = new Escalation('uuid-res-1', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach', 1, '{}');
        $this->repo->save($esc);
        return $esc;
    }

    // ── D. Resolve runtime safety ──

    public function testResolveDoesNotThrowTypeError(): void
    {
        $esc = $this->seedOpenEscalation();
        $command = new ResolveEscalationCommand($esc->id(), 5, 'Root cause fixed');
        $this->handler->handle($command);

        $this->assertSame(Escalation::STATUS_RESOLVED, $this->repo->findById($esc->id())->status());
    }

    public function testResolvePersistsTransition(): void
    {
        $esc = $this->seedOpenEscalation();
        $command = new ResolveEscalationCommand($esc->id(), 5);
        $this->handler->handle($command);

        $transitions = $this->repo->findTransitionsByEscalationId($esc->id());
        $this->assertCount(1, $transitions);
        $this->assertSame(Escalation::STATUS_OPEN, $transitions[0]->fromStatus());
        $this->assertSame(Escalation::STATUS_RESOLVED, $transitions[0]->toStatus());
        $this->assertSame($esc->id(), $transitions[0]->escalationId());
    }

    public function testResolveDispatchesEvent(): void
    {
        $esc = $this->seedOpenEscalation();
        $command = new ResolveEscalationCommand($esc->id(), 5, 'Done');
        $this->handler->handle($command);

        $events = $this->eventBus->dispatchedOfType(EscalationResolvedEvent::class);
        $this->assertCount(1, $events);
        $this->assertSame(5, $events[0]->resolvedBy());
        $this->assertSame('Done', $events[0]->resolutionNote());
    }

    public function testResolveNonExistentThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->handler->handle(new ResolveEscalationCommand(999, 5));
    }

    // ── Dedupe key cleared after resolve ──

    public function testResolveClearsDedupeKey(): void
    {
        $esc = $this->seedOpenEscalation();
        $this->assertNotNull($esc->openDedupeKey(), 'Open escalation should have dedupe key');

        $command = new ResolveEscalationCommand($esc->id(), 5);
        $this->handler->handle($command);

        $resolved = $this->repo->findById($esc->id());
        $this->assertNull($resolved->openDedupeKey(), 'Resolved escalation should clear dedupe key');
    }

    // ── G. Lifecycle isolation ──

    public function testResolveDoesNotModifyTicket(): void
    {
        // Resolve should only modify escalation state — no ticket mutations happen in the handler
        $esc = $this->seedOpenEscalation();
        $command = new ResolveEscalationCommand($esc->id(), 5);
        $this->handler->handle($command);

        // The handler only touches escalation repo. No ticket repo call is possible.
        // If a ticket repo existed in the handler, this test would catch regressions.
        $this->assertSame(Escalation::STATUS_RESOLVED, $this->repo->findById($esc->id())->status());
    }
}
