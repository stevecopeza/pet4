<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Escalation\Event;

use Pet\Domain\Escalation\Event\EscalationTriggeredEvent;
use Pet\Domain\Escalation\Event\EscalationAcknowledgedEvent;
use Pet\Domain\Escalation\Event\EscalationResolvedEvent;
use PHPUnit\Framework\TestCase;

class EscalationEventTest extends TestCase
{
    public function testTriggeredEventProperties(): void
    {
        $event = new EscalationTriggeredEvent('esc-1', 'ticket', 42, 'HIGH', 'SLA breach');

        $this->assertSame('esc-1', $event->escalationId());
        $this->assertSame('ticket', $event->sourceEntityType());
        $this->assertSame(42, $event->sourceEntityId());
        $this->assertSame('HIGH', $event->severity());
        $this->assertSame('SLA breach', $event->reason());
        $this->assertSame(42, $event->aggregateId());
        $this->assertSame('escalation', $event->aggregateType());
        $this->assertNotNull($event->occurredAt());
    }

    public function testTriggeredEventPayload(): void
    {
        $event = new EscalationTriggeredEvent('esc-1', 'ticket', 42, 'HIGH', 'SLA breach');
        $payload = $event->toPayload();

        $this->assertSame('esc-1', $payload['escalation_id']);
        $this->assertSame('ticket', $payload['source_entity_type']);
        $this->assertSame(42, $payload['source_entity_id']);
        $this->assertSame('HIGH', $payload['severity']);
        $this->assertSame('SLA breach', $payload['reason']);
    }

    public function testAcknowledgedEventProperties(): void
    {
        $event = new EscalationAcknowledgedEvent('esc-2', 'project', 10, 'MEDIUM', 5);

        $this->assertSame('esc-2', $event->escalationId());
        $this->assertSame('project', $event->sourceEntityType());
        $this->assertSame(10, $event->sourceEntityId());
        $this->assertSame('MEDIUM', $event->severity());
        $this->assertSame(5, $event->acknowledgedBy());
        $this->assertSame(10, $event->aggregateId());
        $this->assertSame('escalation', $event->aggregateType());
    }

    public function testResolvedEventProperties(): void
    {
        $event = new EscalationResolvedEvent('esc-3', 'customer', 7, 'LOW', 3, 'Root cause fixed');

        $this->assertSame('esc-3', $event->escalationId());
        $this->assertSame('customer', $event->sourceEntityType());
        $this->assertSame(7, $event->sourceEntityId());
        $this->assertSame('LOW', $event->severity());
        $this->assertSame(3, $event->resolvedBy());
        $this->assertSame('Root cause fixed', $event->resolutionNote());
    }

    public function testResolvedEventPayloadIncludesNote(): void
    {
        $event = new EscalationResolvedEvent('esc-3', 'ticket', 1, 'CRITICAL', 2, 'Done');
        $payload = $event->toPayload();

        $this->assertSame('Done', $payload['resolution_note']);
        $this->assertSame(2, $payload['resolved_by']);
    }

    public function testResolvedEventNullNote(): void
    {
        $event = new EscalationResolvedEvent('esc-4', 'ticket', 1, 'LOW', 2);

        $this->assertNull($event->resolutionNote());
        $this->assertNull($event->toPayload()['resolution_note']);
    }
}
