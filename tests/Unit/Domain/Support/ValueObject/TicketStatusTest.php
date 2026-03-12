<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Support\ValueObject;

use Pet\Domain\Support\ValueObject\TicketStatus;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    // ── fromString validation ──

    /** @dataProvider supportStatusesProvider */
    public function testFromStringSupportValid(string $status): void
    {
        $vo = TicketStatus::fromString($status, 'support');
        $this->assertSame($status, $vo->toString());
    }

    public function supportStatusesProvider(): array
    {
        return [['new'], ['open'], ['pending'], ['resolved'], ['closed']];
    }

    /** @dataProvider projectStatusesProvider */
    public function testFromStringProjectValid(string $status): void
    {
        $vo = TicketStatus::fromString($status, 'project');
        $this->assertSame($status, $vo->toString());
    }

    public function projectStatusesProvider(): array
    {
        return [['planned'], ['ready'], ['in_progress'], ['blocked'], ['done'], ['closed']];
    }

    /** @dataProvider internalStatusesProvider */
    public function testFromStringInternalValid(string $status): void
    {
        $vo = TicketStatus::fromString($status, 'internal');
        $this->assertSame($status, $vo->toString());
    }

    public function internalStatusesProvider(): array
    {
        return [['planned'], ['in_progress'], ['done'], ['closed']];
    }

    public function testFromStringRejectsInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TicketStatus::fromString('bogus', 'support');
    }

    public function testFromStringRejectsWrongContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // 'new' is support-only, not valid for project
        TicketStatus::fromString('new', 'project');
    }

    public function testFromStringRejectsUnknownLifecycle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TicketStatus::fromString('open', 'unknown_lifecycle');
    }

    // ── Support lifecycle transitions ──

    public function testSupportNewCanTransitionToOpen(): void
    {
        $status = TicketStatus::fromString('new', 'support');
        $this->assertTrue($status->canTransitionTo('open', 'support'));
        $this->assertFalse($status->canTransitionTo('resolved', 'support'));
    }

    public function testSupportOpenTransitions(): void
    {
        $status = TicketStatus::fromString('open', 'support');
        $this->assertTrue($status->canTransitionTo('pending', 'support'));
        $this->assertTrue($status->canTransitionTo('resolved', 'support'));
        $this->assertTrue($status->canTransitionTo('closed', 'support'));
        $this->assertFalse($status->canTransitionTo('new', 'support'));
    }

    public function testSupportPendingTransitions(): void
    {
        $status = TicketStatus::fromString('pending', 'support');
        $this->assertTrue($status->canTransitionTo('open', 'support'));
        $this->assertTrue($status->canTransitionTo('resolved', 'support'));
        $this->assertTrue($status->canTransitionTo('closed', 'support'));
    }

    public function testSupportResolvedTransitions(): void
    {
        $status = TicketStatus::fromString('resolved', 'support');
        $this->assertTrue($status->canTransitionTo('closed', 'support'));
        $this->assertTrue($status->canTransitionTo('open', 'support')); // reopen
        $this->assertFalse($status->canTransitionTo('new', 'support'));
    }

    public function testSupportClosedIsTerminal(): void
    {
        $status = TicketStatus::fromString('closed', 'support');
        $this->assertTrue($status->isTerminal('support'));
        $this->assertEmpty($status->allowedTransitions('support'));
    }

    // ── Project lifecycle transitions ──

    public function testProjectPlannedToReady(): void
    {
        $status = TicketStatus::fromString('planned', 'project');
        $this->assertTrue($status->canTransitionTo('ready', 'project'));
        $this->assertFalse($status->canTransitionTo('in_progress', 'project'));
    }

    public function testProjectReadyTransitions(): void
    {
        $status = TicketStatus::fromString('ready', 'project');
        $this->assertTrue($status->canTransitionTo('in_progress', 'project'));
        $this->assertTrue($status->canTransitionTo('blocked', 'project'));
        $this->assertFalse($status->canTransitionTo('done', 'project'));
        $this->assertFalse($status->canTransitionTo('planned', 'project'));
    }

    public function testProjectInProgressTransitions(): void
    {
        $status = TicketStatus::fromString('in_progress', 'project');
        $this->assertTrue($status->canTransitionTo('blocked', 'project'));
        $this->assertTrue($status->canTransitionTo('done', 'project'));
        $this->assertFalse($status->canTransitionTo('ready', 'project'));
    }

    public function testProjectBlockedTransitions(): void
    {
        $status = TicketStatus::fromString('blocked', 'project');
        $this->assertTrue($status->canTransitionTo('in_progress', 'project'));
        $this->assertTrue($status->canTransitionTo('ready', 'project'));
        $this->assertFalse($status->canTransitionTo('done', 'project'));
        $this->assertFalse($status->canTransitionTo('planned', 'project'));
    }

    public function testProjectDoneToClosed(): void
    {
        $status = TicketStatus::fromString('done', 'project');
        $this->assertTrue($status->canTransitionTo('closed', 'project'));
        $this->assertFalse($status->canTransitionTo('in_progress', 'project'));
    }

    public function testProjectClosedIsTerminal(): void
    {
        $status = TicketStatus::fromString('closed', 'project');
        $this->assertTrue($status->isTerminal('project'));
    }

    // ── Internal lifecycle transitions ──

    public function testInternalPlannedToInProgress(): void
    {
        $status = TicketStatus::fromString('planned', 'internal');
        $this->assertTrue($status->canTransitionTo('in_progress', 'internal'));
        $this->assertFalse($status->canTransitionTo('done', 'internal'));
    }

    public function testInternalInProgressToDone(): void
    {
        $status = TicketStatus::fromString('in_progress', 'internal');
        $this->assertTrue($status->canTransitionTo('done', 'internal'));
        $this->assertFalse($status->canTransitionTo('closed', 'internal'));
    }

    public function testInternalDoneToClosed(): void
    {
        $status = TicketStatus::fromString('done', 'internal');
        $this->assertTrue($status->canTransitionTo('closed', 'internal'));
    }

    public function testInternalClosedIsTerminal(): void
    {
        $status = TicketStatus::fromString('closed', 'internal');
        $this->assertTrue($status->isTerminal('internal'));
    }

    // ── allForContext ──

    public function testAllForContextSupport(): void
    {
        $all = TicketStatus::allForContext('support');
        $this->assertSame(['new', 'open', 'pending', 'resolved', 'closed'], $all);
    }

    public function testAllForContextProject(): void
    {
        $all = TicketStatus::allForContext('project');
        $this->assertSame(['planned', 'ready', 'in_progress', 'blocked', 'done', 'closed'], $all);
    }

    public function testAllForContextInternal(): void
    {
        $all = TicketStatus::allForContext('internal');
        $this->assertSame(['planned', 'in_progress', 'done', 'closed'], $all);
    }
}
