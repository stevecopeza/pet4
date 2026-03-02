<?php

declare(strict_types=1);

namespace Pet\Application\Support\Service;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\Service\SlaStateResolver;
use Pet\Domain\Sla\Repository\SlaRepository;
use Pet\Domain\Event\EventBus;
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

    public function __construct(
        TicketRepository $ticketRepo,
        SlaClockStateRepository $clockStateRepo,
        SlaRepository $slaRepo,
        EventBus $eventDispatcher,
        FeatureFlagService $featureFlags,
        SqlTransaction $transaction,
        SlaStateResolver $stateResolver
    ) {
        $this->ticketRepo = $ticketRepo;
        $this->clockStateRepo = $clockStateRepo;
        $this->slaRepo = $slaRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->featureFlags = $featureFlags;
        $this->transaction = $transaction;
        $this->stateResolver = $stateResolver;
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
            $this->evaluate($ticket);
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
}
