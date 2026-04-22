<?php

declare(strict_types=1);

namespace Pet\Application\Support\Service;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\Repository\TierTransitionRepository;
use Pet\Domain\Support\Service\SlaStateResolver;
use Pet\Domain\Sla\Repository\SlaRepository;
use Pet\Domain\Sla\Service\TierEvaluator;
use Pet\Domain\Sla\Service\SlaClockService;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Delivery\Event\DeliverySlaWarningEvent;
use Pet\Domain\Delivery\Event\DeliverySlaBreachedEvent;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;
use DateTimeImmutable;

/**
 * Application-layer SLA check orchestration.
 * Coordinates domain logic (SlaStateResolver) with infrastructure (transaction, feature flags, repos).
 */
class SlaCheckService
{
    private TicketRepository $ticketRepo;
    private SlaClockStateRepository $clockStateRepo;
    private SlaRepository $slaRepo;
    private EventBus $eventDispatcher;
    private FeatureFlagService $featureFlags;
    private SqlTransaction $transaction;
    private SlaStateResolver $stateResolver;
    private TierEvaluator $tierEvaluator;
    private SlaClockService $clockService;
    private TierTransitionRepository $tierTransitionRepo;

    public function __construct(
        TicketRepository $ticketRepo,
        SlaClockStateRepository $clockStateRepo,
        SlaRepository $slaRepo,
        EventBus $eventDispatcher,
        FeatureFlagService $featureFlags,
        SqlTransaction $transaction,
        SlaStateResolver $stateResolver,
        TierEvaluator $tierEvaluator,
        SlaClockService $clockService,
        TierTransitionRepository $tierTransitionRepo
    ) {
        $this->ticketRepo = $ticketRepo;
        $this->clockStateRepo = $clockStateRepo;
        $this->slaRepo = $slaRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->featureFlags = $featureFlags;
        $this->transaction = $transaction;
        $this->stateResolver = $stateResolver;
        $this->tierEvaluator = $tierEvaluator;
        $this->clockService = $clockService;
        $this->tierTransitionRepo = $tierTransitionRepo;
    }

    /**
     * Entry point for the SLA automation loop.
     * Finds all active tickets and evaluates their SLA state.
     */
    public function runSlaCheck(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PET SlaCheck] Starting SLA check run...');
        }

        if (!$this->featureFlags->isSlaSchedulerEnabled()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PET SlaCheck] Skipped: SLA Scheduler disabled');
            }
            return;
        }

        $activeTickets = $this->ticketRepo->findActive();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[PET SlaCheck] Found %d active tickets to evaluate', count($activeTickets)));
        }

        foreach ($activeTickets as $ticket) {
            if ($ticket->lifecycleOwner() === 'support') {
                $this->evaluate($ticket);
            } elseif ($ticket->lifecycleOwner() === 'project') {
                $this->evaluateDeliverySla($ticket);
            }
        }
    }

    /**
     * Evaluates the SLA state for a ticket and dispatches events if transitions occur.
     * Idempotent and transactional.
     */
    public function evaluate(Ticket $ticket): void
    {
        $this->transaction->begin();
        try {
            $clockState = $this->clockStateRepo->findByTicketIdForUpdate($ticket->id());

            if (!$clockState) {
                $slaVersionId = 0;
                if ($ticket->slaId()) {
                    $sla = $this->slaRepo->findById($ticket->slaId());
                    if ($sla) {
                        $slaVersionId = $sla->versionNumber();
                    }
                }
                $clockState = $this->clockStateRepo->initialize($ticket, $slaVersionId);
            }

            // Delegate state determination to pure domain service
            $now = new DateTimeImmutable();

            // Tier boundary detection for tiered SLAs
            $this->evaluateTierBoundary($ticket, $clockState, $now);

            $newState = $this->stateResolver->determineState($ticket, $now);

            if ($newState !== $clockState->getLastEventDispatched()) {
                // Delegate transition event resolution to domain service
                $escalationEnabled = $this->featureFlags->isEscalationEngineEnabled();
                $events = $this->stateResolver->resolveTransitionEvents(
                    $ticket,
                    $newState,
                    $clockState,
                    $escalationEnabled
                );

                foreach ($events as $event) {
                    $this->eventDispatcher->dispatch($event);
                }

                // Update escalation stage if an EscalationTriggeredEvent was generated
                foreach ($events as $event) {
                    if ($event instanceof \Pet\Domain\Support\Event\EscalationTriggeredEvent) {
                        $clockState->setEscalationStage(1);
                    }
                }

                $clockState->setLastEventDispatched($newState);
                $clockState->setLastEvaluatedAt($now);
                $this->clockStateRepo->save($clockState);
            }

            $this->transaction->commit();
        } catch (\Exception $e) {
            $this->transaction->rollback();
            throw $e;
        }
    }

    /**
     * Evaluates delivery-ticket SLA status and dispatches transition events.
     *
     * Status values: 'ok' | 'warning' | 'breached'
     * Events fire exactly once per status transition — re-running while already
     * in 'warning' or 'breached' produces no event.
     *
     * The warning threshold matches the inline computation in TicketController
     * (currently +24 hours). If this becomes configurable both must change.
     */
    private const DELIVERY_SLA_WARNING_HOURS = 24;

    private function evaluateDeliverySla(Ticket $ticket): void
    {
        $due = $ticket->resolutionDueAt();
        if ($due === null) {
            return; // No deadline set — nothing to evaluate.
        }

        $now              = new DateTimeImmutable();
        $warningThreshold = $now->modify('+' . self::DELIVERY_SLA_WARNING_HOURS . ' hours');

        if ($due < $now) {
            $newStatus = 'breached';
        } elseif ($due < $warningThreshold) {
            $newStatus = 'warning';
        } else {
            $newStatus = 'ok';
        }

        $previous = $ticket->slaStatus() ?? 'ok';

        if ($newStatus === $previous) {
            return; // No transition — no event, no write.
        }

        $ticket->updateSlaStatus($newStatus);
        $this->ticketRepo->save($ticket);

        $dueIso = $due->format(\DateTimeInterface::ATOM);

        if ($newStatus === 'warning') {
            $this->eventDispatcher->dispatch(
                new DeliverySlaWarningEvent($ticket->id(), $ticket->projectId(), $dueIso)
            );
        } elseif ($newStatus === 'breached') {
            $this->eventDispatcher->dispatch(
                new DeliverySlaBreachedEvent($ticket->id(), $ticket->projectId(), $dueIso)
            );
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[PET SlaCheck] Delivery ticket #%d: %s → %s (due %s)',
                $ticket->id(),
                $previous,
                $newStatus,
                $dueIso
            ));
        }
    }

    /**
     * Check for tier boundary crossing on tiered SLAs and handle transition.
     */
    private function evaluateTierBoundary(
        Ticket $ticket,
        \Pet\Domain\Support\Entity\SlaClockState $clockState,
        DateTimeImmutable $now
    ): void {
        if (!$ticket->slaId()) {
            return;
        }

        $snapshot = $this->slaRepo->findSnapshotById($clockState->getSlaVersionId());
        if (!$snapshot || !$snapshot->isTiered()) {
            return;
        }

        // Build calendar snapshots keyed by tier priority
        // In production, these would be loaded from the snapshot's tier data
        $calendarSnapshotsByTier = $this->buildCalendarSnapshotsByTier($snapshot);

        $newTierPriority = $this->stateResolver->detectTierBoundary(
            $now,
            $clockState,
            $snapshot,
            $this->tierEvaluator,
            $calendarSnapshotsByTier
        );

        if ($newTierPriority === null) {
            return;
        }

        // Handle the transition
        $result = $this->stateResolver->handleTierTransition(
            $ticket,
            $clockState,
            $snapshot,
            $newTierPriority,
            $this->tierEvaluator,
            $this->clockService,
            $calendarSnapshotsByTier,
            $now
        );

        // Persist transition record
        $this->tierTransitionRepo->save($result['transition']);

        // Update clock state
        $updates = $result['clock_updates'];
        $clockState->setActiveTierPriority($updates['active_tier_priority']);
        $clockState->setTierElapsedBusinessMinutes($updates['tier_elapsed_business_minutes']);
        $clockState->setCarriedForwardPercent($updates['carried_forward_percent']);
        $clockState->incrementTransitions();

        // Recalculate due dates on the ticket
        $newTier = $snapshot->findTierByPriority($newTierPriority);
        $newCalendar = $calendarSnapshotsByTier[$newTierPriority] ?? [];
        if ($newTier && !empty($newCalendar)) {
            $newResponseDue = $this->clockService->recalculateDueAfterTransition(
                $now,
                $updates['remaining_minutes'],
                $newCalendar
            );
            // The ticket due dates would be updated via the ticket repository
            // This is a simplified representation — actual implementation would
            // call $ticket->setResolutionDueAt($newResponseDue) etc.
        }

        // Dispatch events
        foreach ($result['events'] as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        // Save updated clock state
        $this->clockStateRepo->save($clockState);
    }

    /**
     * Build calendar snapshot lookup keyed by tier priority.
     * Each tier in the snapshot stores its own calendar data.
     */
    private function buildCalendarSnapshotsByTier(\Pet\Domain\Sla\Entity\SlaSnapshot $snapshot): array
    {
        $map = [];
        foreach ($snapshot->tierSnapshots() ?? [] as $tier) {
            $priority = $tier['priority'] ?? 0;
            // The calendar snapshot is stored per-tier in the snapshot data
            // For tiers that reference a calendar_id, we need the calendar snapshot
            // In the current design, this is embedded in the tier snapshot itself
            $map[$priority] = $tier['calendar_snapshot'] ?? $tier;
        }
        return $map;
    }
}
