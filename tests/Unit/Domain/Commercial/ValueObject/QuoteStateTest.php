<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial\ValueObject;

use Pet\Domain\Commercial\ValueObject\QuoteState;
use PHPUnit\Framework\TestCase;

class QuoteStateTest extends TestCase
{
    // ── Factory methods ──

    public function testDraftFactory(): void
    {
        $state = QuoteState::draft();
        $this->assertSame('draft', $state->toString());
    }

    public function testSentFactory(): void
    {
        $state = QuoteState::sent();
        $this->assertSame('sent', $state->toString());
    }

    public function testAcceptedFactory(): void
    {
        $state = QuoteState::accepted();
        $this->assertSame('accepted', $state->toString());
    }

    public function testRejectedFactory(): void
    {
        $state = QuoteState::rejected();
        $this->assertSame('rejected', $state->toString());
    }

    public function testArchivedFactory(): void
    {
        $state = QuoteState::archived();
        $this->assertSame('archived', $state->toString());
    }

    // ── fromString ──

    /** @dataProvider validStatesProvider */
    public function testFromStringValid(string $value): void
    {
        $state = QuoteState::fromString($value);
        $this->assertSame($value, $state->toString());
    }

    public function validStatesProvider(): array
    {
        return [
            ['draft'],
            ['pending_approval'],
            ['approved'],
            ['sent'],
            ['accepted'],
            ['rejected'],
            ['archived'],
        ];
    }

    public function testFromStringInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        QuoteState::fromString('bogus');
    }

    // ── Transitions ──

    // DRAFT transitions
    public function testDraftCannotTransitionToSentDirectly(): void
    {
        // DRAFT → SENT is intentionally blocked — approval is mandatory.
        // The required path is DRAFT → PENDING_APPROVAL → APPROVED → SENT.
        $this->assertFalse(QuoteState::draft()->canTransitionTo(QuoteState::sent()));
    }

    public function testDraftCanTransitionToPendingApproval(): void
    {
        $this->assertTrue(QuoteState::draft()->canTransitionTo(QuoteState::pendingApproval()));
    }

    public function testDraftCanTransitionToArchived(): void
    {
        $this->assertTrue(QuoteState::draft()->canTransitionTo(QuoteState::archived()));
    }

    public function testDraftCannotTransitionToAccepted(): void
    {
        $this->assertFalse(QuoteState::draft()->canTransitionTo(QuoteState::accepted()));
    }

    public function testDraftCannotTransitionToRejected(): void
    {
        $this->assertFalse(QuoteState::draft()->canTransitionTo(QuoteState::rejected()));
    }

    // PENDING_APPROVAL transitions
    public function testPendingApprovalCanTransitionToApproved(): void
    {
        $this->assertTrue(QuoteState::pendingApproval()->canTransitionTo(QuoteState::approved()));
    }

    public function testPendingApprovalCanTransitionBackToDraft(): void
    {
        // Rejection returns to draft with a note
        $this->assertTrue(QuoteState::pendingApproval()->canTransitionTo(QuoteState::draft()));
    }

    public function testPendingApprovalCannotTransitionToSent(): void
    {
        $this->assertFalse(QuoteState::pendingApproval()->canTransitionTo(QuoteState::sent()));
    }

    // APPROVED transitions
    public function testApprovedCanTransitionToSent(): void
    {
        $this->assertTrue(QuoteState::approved()->canTransitionTo(QuoteState::sent()));
    }

    public function testApprovedCanRevertToDraft(): void
    {
        $this->assertTrue(QuoteState::approved()->canTransitionTo(QuoteState::draft()));
    }

    public function testApprovedCannotTransitionToAccepted(): void
    {
        $this->assertFalse(QuoteState::approved()->canTransitionTo(QuoteState::accepted()));
    }

    public function testSentCanTransitionToAccepted(): void
    {
        $this->assertTrue(QuoteState::sent()->canTransitionTo(QuoteState::accepted()));
    }

    public function testSentCanTransitionToRejected(): void
    {
        $this->assertTrue(QuoteState::sent()->canTransitionTo(QuoteState::rejected()));
    }

    public function testSentCanTransitionToArchived(): void
    {
        $this->assertTrue(QuoteState::sent()->canTransitionTo(QuoteState::archived()));
    }

    public function testSentCanTransitionToDraft(): void
    {
        $this->assertTrue(QuoteState::sent()->canTransitionTo(QuoteState::draft()));
    }

    /** @dataProvider terminalStatesProvider */
    public function testTerminalStatesCannotTransition(string $terminalState): void
    {
        $state = QuoteState::fromString($terminalState);
        foreach (['draft', 'sent', 'accepted', 'rejected', 'archived'] as $target) {
            $this->assertFalse(
                $state->canTransitionTo(QuoteState::fromString($target)),
                "$terminalState should not transition to $target"
            );
        }
    }

    public function terminalStatesProvider(): array
    {
        return [
            ['accepted'],
            ['rejected'],
            ['archived'],
        ];
    }

    // ── isTerminal ──

    public function testDraftIsNotTerminal(): void
    {
        $this->assertFalse(QuoteState::draft()->isTerminal());
    }

    public function testSentIsNotTerminal(): void
    {
        $this->assertFalse(QuoteState::sent()->isTerminal());
    }

    /** @dataProvider terminalStatesProvider */
    public function testTerminalStates(string $state): void
    {
        $this->assertTrue(QuoteState::fromString($state)->isTerminal());
    }
}
