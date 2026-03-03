<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Service;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Support\Entity\TierTransition;
use Pet\Domain\Support\ValueObject\SlaState;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;
use Pet\Domain\Support\Event\SLATierTransitionedEvent;
use Pet\Domain\Sla\Entity\SlaSnapshot;
use Pet\Domain\Sla\Service\TierEvaluator;
use Pet\Domain\Sla\Service\SlaClockService;
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
        $tierPriority = $clockState->getActiveTierPriority();

        if ($newState === SlaState::WARNING) {
            $events[] = new TicketWarningEvent($ticket->id(), $tierPriority);
        } elseif ($newState === SlaState::BREACHED) {
            $events[] = new TicketBreachedEvent($ticket->id(), $tierPriority);

            if ($escalationEnabled && $clockState->getEscalationStage() < 1) {
                $events[] = new EscalationTriggeredEvent($ticket->id(), 1, $tierPriority);
            }
        }

        return $events;
    }

    /**
     * Detect if the ticket has crossed a tier boundary.
     * Returns the new tier priority if a boundary was crossed, null otherwise.
     */
    public function detectTierBoundary(
        DateTimeImmutable $now,
        SlaClockState $clockState,
        SlaSnapshot $snapshot,
        TierEvaluator $tierEvaluator,
        array $calendarSnapshotsByTier
    ): ?int {
        if (!$snapshot->isTiered()) {
            return null;
        }

        $currentTierPriority = $clockState->getActiveTierPriority();
        $newTierPriority = $tierEvaluator->selectTier(
            $now,
            $snapshot->tierSnapshots(),
            $calendarSnapshotsByTier
        );

        // No change or no matching tier
        if ($newTierPriority === null || $newTierPriority === $currentTierPriority) {
            return null;
        }

        return $newTierPriority;
    }

    /**
     * Handle a tier transition: calculate carry-forward, build events, return transition record.
     *
     * @return array{transition: TierTransition, events: object[], clock_updates: array}
     */
    public function handleTierTransition(
        Ticket $ticket,
        SlaClockState $clockState,
        SlaSnapshot $snapshot,
        int $newTierPriority,
        TierEvaluator $tierEvaluator,
        SlaClockService $clockService,
        array $calendarSnapshotsByTier,
        DateTimeImmutable $now,
        ?string $overrideReason = null
    ): array {
        $currentTierPriority = $clockState->getActiveTierPriority();
        $elapsedMinutes = $clockState->getTierElapsedBusinessMinutes();
        $capPercent = $snapshot->tierTransitionCapPercent() ?? 80;

        // Get current and new tier data
        $currentTier = $currentTierPriority !== null ? $snapshot->findTierByPriority($currentTierPriority) : null;
        $newTier = $snapshot->findTierByPriority($newTierPriority);

        if ($newTier === null) {
            throw new \DomainException("Target tier priority {$newTierPriority} not found in snapshot.");
        }

        // Calculate carry-forward (use resolution target as the basis)
        $currentTarget = $currentTier ? ($currentTier['resolution_target_minutes'] ?? 0) : 0;
        $newTarget = $newTier['resolution_target_minutes'] ?? 0;

        $transitionData = $tierEvaluator->calculateTransition(
            $elapsedMinutes,
            $currentTarget,
            $newTarget,
            $capPercent
        );

        // Build transition record
        $actualPercent = $currentTarget > 0 ? ($elapsedMinutes / $currentTarget) * 100 : 0;
        $transition = new TierTransition(
            $ticket->id(),
            $currentTierPriority,
            $newTierPriority,
            round($actualPercent, 2),
            $transitionData['carried_percent'],
            $overrideReason,
            $now
        );

        // Build event
        $events = [
            new SLATierTransitionedEvent(
                $ticket->id(),
                $currentTierPriority,
                $newTierPriority,
                $transitionData['carried_percent'],
                $overrideReason
            ),
        ];

        // Clock state updates to apply
        $clockUpdates = [
            'active_tier_priority' => $newTierPriority,
            'tier_elapsed_business_minutes' => $transitionData['equivalent_elapsed'],
            'carried_forward_percent' => $transitionData['carried_percent'],
            'remaining_minutes' => $transitionData['remaining_minutes'],
        ];

        return [
            'transition' => $transition,
            'events' => $events,
            'clock_updates' => $clockUpdates,
        ];
    }
}
