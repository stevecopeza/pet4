<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Escalation\Entity;

use Pet\Domain\Escalation\Entity\Escalation;
use PHPUnit\Framework\TestCase;

class EscalationDedupeTest extends TestCase
{
    // ── Dedupe key computation ──

    public function testDedupeKeyIsDeterministic(): void
    {
        $key1 = Escalation::computeDedupeKey('ticket', 42, 'HIGH', 'SLA breach');
        $key2 = Escalation::computeDedupeKey('ticket', 42, 'HIGH', 'SLA breach');

        $this->assertSame($key1, $key2);
        $this->assertSame(64, strlen($key1));
    }

    public function testDedupeKeyDiffersForDifferentInputs(): void
    {
        $key1 = Escalation::computeDedupeKey('ticket', 42, 'HIGH', 'SLA breach');
        $key2 = Escalation::computeDedupeKey('ticket', 42, 'CRITICAL', 'SLA breach');
        $key3 = Escalation::computeDedupeKey('ticket', 99, 'HIGH', 'SLA breach');
        $key4 = Escalation::computeDedupeKey('project', 42, 'HIGH', 'SLA breach');

        $this->assertNotSame($key1, $key2, 'Different severity');
        $this->assertNotSame($key1, $key3, 'Different entity ID');
        $this->assertNotSame($key1, $key4, 'Different entity type');
    }

    // ── Dedupe key lifecycle ──

    public function testOpenEscalationHasDedupeKey(): void
    {
        $esc = new Escalation('uuid-1', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach');
        $this->assertNotNull($esc->openDedupeKey());
    }

    public function testResolvedEscalationHasNoDedupeKey(): void
    {
        $esc = new Escalation('uuid-1', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach', null, '{}', 1, Escalation::STATUS_RESOLVED);
        $this->assertNull($esc->openDedupeKey());
    }

    public function testResolveClearsDedupeKey(): void
    {
        $esc = new Escalation('uuid-1', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach', null, '{}', 1);
        $this->assertNotNull($esc->openDedupeKey());

        $esc->resolve(5);
        $this->assertNull($esc->openDedupeKey());
    }

    public function testAcknowledgePreservesDedupeKey(): void
    {
        $esc = new Escalation('uuid-1', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach', null, '{}', 1);
        $keyBefore = $esc->openDedupeKey();

        $esc->acknowledge(5);

        $this->assertSame($keyBefore, $esc->openDedupeKey());
    }

    // ── G. Lifecycle isolation — entity has no ticket awareness ──

    public function testEscalationEntityHasNoTicketMutationMethods(): void
    {
        $esc = new Escalation('uuid-1', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach');
        $ref = new \ReflectionClass($esc);
        $methods = array_map(fn(\ReflectionMethod $m) => $m->getName(), $ref->getMethods());

        $ticketMethods = ['setStatus', 'setLifecycleOwner', 'assign', 'setAssignment'];
        foreach ($ticketMethods as $forbidden) {
            $this->assertNotContains($forbidden, $methods, "Escalation should not have $forbidden method");
        }
    }
}
