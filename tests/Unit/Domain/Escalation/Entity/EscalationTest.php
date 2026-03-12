<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Escalation\Entity;

use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Event\EscalationAcknowledgedEvent;
use Pet\Domain\Escalation\Event\EscalationResolvedEvent;
use PHPUnit\Framework\TestCase;

class EscalationTest extends TestCase
{
    private function makeEscalation(string $status = Escalation::STATUS_OPEN): Escalation
    {
        $esc = new Escalation(
            'uuid-001',
            'ticket',
            42,
            Escalation::SEVERITY_HIGH,
            'SLA breach',
            1,
            '{}',
            10,
            $status
        );
        return $esc;
    }

    // ── Construction validation ──

    public function testConstructRejectsInvalidSourceType(): void
    {
        $this->expectException(\DomainException::class);
        new Escalation('uuid', 'invalid_type', 1, Escalation::SEVERITY_LOW, 'reason');
    }

    public function testConstructRejectsInvalidSeverity(): void
    {
        $this->expectException(\DomainException::class);
        new Escalation('uuid', 'ticket', 1, 'BOGUS', 'reason');
    }

    public function testConstructRejectsInvalidStatus(): void
    {
        $this->expectException(\DomainException::class);
        new Escalation('uuid', 'ticket', 1, Escalation::SEVERITY_LOW, 'reason', null, '{}', null, 'INVALID');
    }

    // ── Transitions ──

    public function testOpenCanAcknowledge(): void
    {
        $esc = $this->makeEscalation();
        $esc->acknowledge(5);

        $this->assertSame(Escalation::STATUS_ACKED, $esc->status());
        $this->assertSame(5, $esc->acknowledgedBy());
        $this->assertNotNull($esc->acknowledgedAt());
    }

    public function testOpenCanResolveDirectly(): void
    {
        $esc = $this->makeEscalation();
        $esc->resolve(5, 'Fixed it');

        $this->assertSame(Escalation::STATUS_RESOLVED, $esc->status());
        $this->assertSame(5, $esc->resolvedBy());
        $this->assertNotNull($esc->resolvedAt());
        $this->assertTrue($esc->isTerminal());
    }

    public function testAckedCanResolve(): void
    {
        $esc = $this->makeEscalation();
        $esc->acknowledge(5);
        $esc->releaseEvents(); // clear
        $esc->resolve(6, 'Done');

        $this->assertSame(Escalation::STATUS_RESOLVED, $esc->status());
        $this->assertSame(6, $esc->resolvedBy());
    }

    public function testResolvedCannotAcknowledge(): void
    {
        $esc = $this->makeEscalation();
        $esc->resolve(5);

        $this->expectException(\DomainException::class);
        $esc->acknowledge(6);
    }

    public function testResolvedCannotReResolve(): void
    {
        $esc = $this->makeEscalation();
        $esc->resolve(5);

        $this->expectException(\DomainException::class);
        $esc->resolve(6);
    }

    public function testAckedCannotReAcknowledge(): void
    {
        $esc = $this->makeEscalation();
        $esc->acknowledge(5);

        $this->expectException(\DomainException::class);
        $esc->acknowledge(6);
    }

    // ── Domain events ──

    public function testAcknowledgeRecordsEvent(): void
    {
        $esc = $this->makeEscalation();
        $esc->acknowledge(5);

        $events = $esc->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(EscalationAcknowledgedEvent::class, $events[0]);
    }

    public function testResolveRecordsEvent(): void
    {
        $esc = $this->makeEscalation();
        $esc->resolve(5, 'note');

        $events = $esc->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(EscalationResolvedEvent::class, $events[0]);
    }

    public function testReleaseEventsClearsList(): void
    {
        $esc = $this->makeEscalation();
        $esc->acknowledge(5);

        $events = $esc->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertCount(0, $esc->releaseEvents());
    }

    // ── Getters ──

    public function testGettersReturnConstructedValues(): void
    {
        $esc = $this->makeEscalation();

        $this->assertSame(10, $esc->id());
        $this->assertSame('uuid-001', $esc->escalationId());
        $this->assertSame('ticket', $esc->sourceEntityType());
        $this->assertSame(42, $esc->sourceEntityId());
        $this->assertSame(Escalation::SEVERITY_HIGH, $esc->severity());
        $this->assertSame(Escalation::STATUS_OPEN, $esc->status());
        $this->assertSame('SLA breach', $esc->reason());
        $this->assertSame('{}', $esc->metadataJson());
        $this->assertSame(1, $esc->createdBy());
        $this->assertFalse($esc->isTerminal());
    }

    // ── Valid source types ──

    public function testAllValidSourceTypes(): void
    {
        foreach (['ticket', 'project', 'customer'] as $type) {
            $esc = new Escalation('uuid', $type, 1, Escalation::SEVERITY_LOW, 'reason');
            $this->assertSame($type, $esc->sourceEntityType());
        }
    }

    // ── Valid severities ──

    public function testAllValidSeverities(): void
    {
        foreach ([Escalation::SEVERITY_LOW, Escalation::SEVERITY_MEDIUM, Escalation::SEVERITY_HIGH, Escalation::SEVERITY_CRITICAL] as $sev) {
            $esc = new Escalation('uuid', 'ticket', 1, $sev, 'reason');
            $this->assertSame($sev, $esc->severity());
        }
    }
}
