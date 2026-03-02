<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Service;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\ValueObject\SlaState;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Sla\Repository\SlaRepository;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;
use DateTimeImmutable;

class SlaAutomationService
{
    private TicketRepository $ticketRepo;
    private SlaClockStateRepository $clockStateRepo;
    private SlaRepository $slaRepo;
    private EventBus $eventDispatcher;
    private FeatureFlagService $featureFlags;
    private SqlTransaction $transaction;

    public function __construct(
        TicketRepository $ticketRepo,
        SlaClockStateRepository $clockStateRepo,
        SlaRepository $slaRepo,
        EventBus $eventDispatcher,
        FeatureFlagService $featureFlags,
        SqlTransaction $transaction
    ) {
        $this->ticketRepo = $ticketRepo;
        $this->clockStateRepo = $clockStateRepo;
        $this->slaRepo = $slaRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->featureFlags = $featureFlags;
        $this->transaction = $transaction;
    }

    /**
     * Entry point for the SLA automation loop.
     * Finds all active tickets and evaluates their SLA state.
     */
    public function runSlaCheck(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PET SlaAutomation] Starting SLA check run...');
        }

        if (!$this->featureFlags->isSlaSchedulerEnabled()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PET SlaAutomation] Skipped: SLA Scheduler disabled');
            }
            return;
        }

        $activeTickets = $this->ticketRepo->findActive();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[PET SlaAutomation] Found %d active tickets to evaluate', count($activeTickets)));
        }

        foreach ($activeTickets as $ticket) {
            $this->evaluate($ticket);
        }
    }

    /**
     * Evaluates the SLA state for a ticket and dispatches events if transitions occur.
     * This method is idempotent and stateful.
     */
    public function evaluate(Ticket $ticket): void
    {
        $this->transaction->begin();
        try {
            // 2. Load persisted state with row locking
            $clockState = $this->clockStateRepo->findByTicketIdForUpdate($ticket->id());
            
            if (!$clockState) {
                // First evaluation, initialize state
                $slaVersionId = 0;
                if ($ticket->slaId()) {
                    $sla = $this->slaRepo->findById($ticket->slaId());
                    if ($sla) {
                        $slaVersionId = $sla->versionNumber();
                    }
                }
                
                $clockState = $this->clockStateRepo->initialize($ticket, $slaVersionId);
            }

            // 3. Determine new state
            $newState = $this->determineState($ticket, $clockState);
            
            // 4. Compare and Transition
            if ($newState !== $clockState->getLastEventDispatched()) {
                $this->handleTransition($ticket, $newState, $clockState);
                
                // 5. Persist new state
                $clockState->setLastEventDispatched($newState);
                $clockState->setLastEvaluatedAt(new DateTimeImmutable());
                $this->clockStateRepo->save($clockState);
            }
            $this->transaction->commit();
        } catch (\Exception $e) {
            $this->transaction->rollback();
            throw $e;
        }
    }

    /**
     * Protected for testing purposes to simulate different time states
     */
    protected function determineState(Ticket $ticket, SlaClockState $clockState): string
    {
        $now = $this->getNow();
        
        // 1. Check for Paused Status
        // If ticket status implies pause (e.g., pending input), return PAUSED
        // Assuming 'pending' or 'on_hold' are paused states.
        // Also handling 'resolved' and 'closed' as effectively paused/stopped to prevent late breaches.
        if (in_array($ticket->status(), ['pending', 'on_hold', 'resolved', 'closed'], true)) {
            return SlaState::PAUSED;
        }

        // 2. Check Resolution Breach
        if ($ticket->resolutionDueAt() && $now > $ticket->resolutionDueAt()) {
            return SlaState::BREACHED;
        }

        // 3. Check Response Breach (if not yet responded)
        if ($ticket->responseDueAt() && !$ticket->respondedAt() && $now > $ticket->responseDueAt()) {
            return SlaState::BREACHED;
        }

        // 4. Check Warning (e.g. 1 hour before breach)
        // Hardcoded 1 hour warning for now as per requirements/simplicity
        if ($ticket->resolutionDueAt()) {
            $warningThreshold = $ticket->resolutionDueAt()->modify('-1 hour');
            if ($now >= $warningThreshold) {
                return SlaState::WARNING;
            }
        }

        return SlaState::ACTIVE;
    }

    private function handleTransition(Ticket $ticket, string $newState, SlaClockState $clockState): void
    {
        // Only dispatch if transitioning to a worse state or explicitly handled
        // Transitions: Active -> Warning -> Breached
        
        if ($newState === SlaState::WARNING) {
            $this->eventDispatcher->dispatch(new TicketWarningEvent($ticket->id(), new DateTimeImmutable()));
        } elseif ($newState === SlaState::BREACHED) {
            $this->eventDispatcher->dispatch(new TicketBreachedEvent($ticket->id(), new DateTimeImmutable()));

            if ($this->featureFlags->isEscalationEngineEnabled() && $clockState->getEscalationStage() < 1) {
                $this->eventDispatcher->dispatch(new EscalationTriggeredEvent($ticket->id(), 1));
                $clockState->setEscalationStage(1);
            }
        } elseif ($newState === SlaState::PAUSED) {
            // No event for pause currently required
        }
    }

    protected function getNow(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
