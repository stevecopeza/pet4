<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Support\Entity;

use Pet\Domain\Support\Entity\Ticket;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Ticket::canAcceptTimeEntries() — lifecycle-gated time logging.
 *
 * Rules:
 * - Only leaf tickets (not rollup) may accept time.
 * - Support tickets require an operational owner and must not be closed.
 * - Non-support tickets require 'in_progress' status.
 */
class TicketTimeLoggingTest extends TestCase
{
    private function makeTicket(
        string $status = 'new',
        bool $isRollup = false,
        string $lifecycleOwner = 'support',
        ?string $queueId = null,
        ?string $ownerUserId = null
    ): Ticket {
        return new Ticket(
            customerId: 1,
            subject: 'Test ticket',
            description: 'Test description',
            status: $status,
            priority: 'medium',
            siteId: null,
            slaId: null,
            id: 1,
            queueId: $queueId,
            ownerUserId: $ownerUserId,
            lifecycleOwner: $lifecycleOwner,
            isRollup: $isRollup
        );
    }

    // ── Leaf + in_progress: should accept time ──

    public function testLeafInProgressAcceptsTime(): void
    {
        $ticket = $this->makeTicket(status: 'in_progress', lifecycleOwner: 'project');
        $this->assertTrue($ticket->canAcceptTimeEntries());
    }

    public function testLeafInProgressInternalAcceptsTime(): void
    {
        $ticket = $this->makeTicket(status: 'in_progress', lifecycleOwner: 'internal');
        $this->assertTrue($ticket->canAcceptTimeEntries());
    }

    // ── Rollup: never accepts time regardless of status ──

    public function testRollupInProgressRejectsTime(): void
    {
        $ticket = $this->makeTicket(status: 'in_progress', isRollup: true, lifecycleOwner: 'project');
        $this->assertFalse($ticket->canAcceptTimeEntries());
    }

    // ── Non-executable statuses: reject time even for leaves ──

    public function testLeafPlannedRejectsTime(): void
    {
        $ticket = $this->makeTicket(status: 'planned', lifecycleOwner: 'project');
        $this->assertFalse($ticket->canAcceptTimeEntries());
    }

    public function testLeafReadyRejectsTime(): void
    {
        $ticket = $this->makeTicket(status: 'ready', lifecycleOwner: 'project');
        $this->assertFalse($ticket->canAcceptTimeEntries());
    }

    public function testLeafBlockedRejectsTime(): void
    {
        $ticket = $this->makeTicket(status: 'blocked', lifecycleOwner: 'project');
        $this->assertFalse($ticket->canAcceptTimeEntries());
    }

    public function testLeafDoneRejectsTime(): void
    {
        $ticket = $this->makeTicket(status: 'done', lifecycleOwner: 'project');
        $this->assertFalse($ticket->canAcceptTimeEntries());
    }

    public function testLeafClosedRejectsTime(): void
    {
        $ticket = $this->makeTicket(status: 'closed', lifecycleOwner: 'project');
        $this->assertFalse($ticket->canAcceptTimeEntries());
    }

    // ── Support context: only in_progress-equivalent statuses ──

    public function testSupportUnassignedRejectsTime(): void
    {
        $ticket = $this->makeTicket(status: 'open', lifecycleOwner: 'support');
        $this->assertFalse($ticket->canAcceptTimeEntries());
    }

    public function testSupportAssignedToTeamAcceptsTime(): void
    {
        $ticket = $this->makeTicket(status: 'open', lifecycleOwner: 'support', queueId: '3', ownerUserId: null);
        $this->assertTrue($ticket->canAcceptTimeEntries());
    }

    public function testSupportAssignedToUserAcceptsTime(): void
    {
        $ticket = $this->makeTicket(status: 'pending', lifecycleOwner: 'support', queueId: null, ownerUserId: '10');
        $this->assertTrue($ticket->canAcceptTimeEntries());
    }

    public function testSupportClosedRejectsTime(): void
    {
        $ticket = $this->makeTicket(status: 'closed', lifecycleOwner: 'support', queueId: '3', ownerUserId: null);
        $this->assertFalse($ticket->canAcceptTimeEntries());
    }
}
