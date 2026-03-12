<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Escalation\Command;

use Pet\Application\Escalation\Command\AcknowledgeEscalationCommand;
use Pet\Application\Escalation\Command\AcknowledgeEscalationHandler;
use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Event\EscalationAcknowledgedEvent;
use Pet\Tests\Stub\FakeTransactionManager;
use Pet\Tests\Stub\InMemoryEscalationRepository;
use Pet\Tests\Stub\SpyEventBus;
use PHPUnit\Framework\TestCase;

class AcknowledgeEscalationHandlerTest extends TestCase
{
    private InMemoryEscalationRepository $repo;
    private SpyEventBus $eventBus;
    private AcknowledgeEscalationHandler $handler;

    protected function setUp(): void
    {
        $this->repo = new InMemoryEscalationRepository();
        $this->eventBus = new SpyEventBus();
        $this->handler = new AcknowledgeEscalationHandler(
            new FakeTransactionManager(),
            $this->repo,
            $this->eventBus
        );
    }

    private function seedOpenEscalation(): Escalation
    {
        $esc = new Escalation('uuid-ack-1', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach', 1, '{}');
        $this->repo->save($esc);
        return $esc;
    }

    // ── D. Acknowledge runtime safety ──

    public function testAcknowledgeDoesNotThrowTypeError(): void
    {
        $esc = $this->seedOpenEscalation();

        // This used to throw TypeError because id() returns ?int and EscalationTransition expected string
        $command = new AcknowledgeEscalationCommand($esc->id(), 5);
        $this->handler->handle($command);

        $this->assertSame(Escalation::STATUS_ACKED, $this->repo->findById($esc->id())->status());
    }

    public function testAcknowledgePersistsTransition(): void
    {
        $esc = $this->seedOpenEscalation();
        $command = new AcknowledgeEscalationCommand($esc->id(), 5);
        $this->handler->handle($command);

        $transitions = $this->repo->findTransitionsByEscalationId($esc->id());
        $this->assertCount(1, $transitions);
        $this->assertSame(Escalation::STATUS_OPEN, $transitions[0]->fromStatus());
        $this->assertSame(Escalation::STATUS_ACKED, $transitions[0]->toStatus());
        $this->assertSame($esc->id(), $transitions[0]->escalationId());
    }

    public function testAcknowledgeDispatchesEvent(): void
    {
        $esc = $this->seedOpenEscalation();
        $command = new AcknowledgeEscalationCommand($esc->id(), 5);
        $this->handler->handle($command);

        $events = $this->eventBus->dispatchedOfType(EscalationAcknowledgedEvent::class);
        $this->assertCount(1, $events);
        $this->assertSame(5, $events[0]->acknowledgedBy());
    }

    public function testAcknowledgeNonExistentThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->handler->handle(new AcknowledgeEscalationCommand(999, 5));
    }
}
