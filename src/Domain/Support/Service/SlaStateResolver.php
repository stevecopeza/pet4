<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Service;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Support\ValueObject\SlaState;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;
use DateTimeImmutable;

/**
 * Pure domain service for SLA state evaluation.
 * No infrastructure dependencies — accepts only domain objects.
 */
class SlaStateResolver
{
    /**
     * Determine the SLA state for a ticket based on current time.
     */
    public function determineState(Ticket $ticket, DateTimeImmutable $now): string
    {
        // Paused when ticket is in a holding/terminal status
        if (in_array($ticket->status(), ['pending', 'on_hold', 'resolved', 'closed'], true)) {
            return SlaState::PAUSED;
        }

        // Resolution breach
        if ($ticket->resolutionDueAt() && $now > $ticket->resolutionDueAt()) {
            return SlaState::BREACHED;
        }

        // Response breach (not yet responded)
        if ($ticket->responseDueAt() && !$ticket->respondedAt() && $now > $ticket->responseDueAt()) {
            return SlaState::BREACHED;
        }

        // Warning threshold (1 hour before resolution due)
        if ($ticket->resolutionDueAt()) {
            $warningThreshold = $ticket->resolutionDueAt()->modify('-1 hour');
            if ($now >= $warningThreshold) {
                return SlaState::WARNING;
            }
        }

        return SlaState::ACTIVE;
    }

    /**
     * Build domain events for a state transition.
     *
     * @return object[] Array of domain events to dispatch
     */
    public function resolveTransitionEvents(
        Ticket $ticket,
        string $newState,
        SlaClockState $clockState,
        bool $escalationEnabled
    ): array {
        $events = [];

        if ($newState === SlaState::WARNING) {
            $events[] = new TicketWarningEvent($ticket->id(), new DateTimeImmutable());
        } elseif ($newState === SlaState::BREACHED) {
            $events[] = new TicketBreachedEvent($ticket->id(), new DateTimeImmutable());

            if ($escalationEnabled && $clockState->getEscalationStage() < 1) {
                $events[] = new EscalationTriggeredEvent($ticket->id(), 1);
            }
        }

        return $events;
    }
}
