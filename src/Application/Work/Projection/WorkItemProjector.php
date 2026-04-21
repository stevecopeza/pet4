<?php

namespace Pet\Application\Work\Projection;

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Entity\DepartmentQueue;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Repository\DepartmentQueueRepository;
use Pet\Domain\Work\Service\DepartmentResolver;
use Pet\Domain\Work\Service\SlaClockCalculator;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Support\Event\TicketCreated;
use Pet\Domain\Support\Event\TicketAssigned;
use Pet\Application\System\Service\FeatureFlagService;

/**
 * WorkItem Projector.
 * 
 * Handles events from source domains (Commercial, Delivery, Support)
 * and projects them into the WorkItem and DepartmentQueue read models.
 */
class WorkItemProjector
{
    public function __construct(
        private WorkItemRepository $workItemRepository,
        private DepartmentQueueRepository $departmentQueueRepository,
        private DepartmentResolver $departmentResolver,
        private SlaClockCalculator $slaClockCalculator,
        private CustomerRepository $customerRepository,
        private FeatureFlagService $featureFlags
    ) {
    }

    public function onTicketCreated(TicketCreated $event): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PET WorkProjection] TicketCreated event received for ticket: ' . $event->ticket()->id());
        }

        if (!$this->featureFlags->isWorkProjectionEnabled()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PET WorkProjection] Skipped: Work Projection disabled');
            }
            return;
        }

        $ticket = $event->ticket();
        
        // Idempotency Check: Prevent duplicate projection
        $existing = $this->workItemRepository->findBySource('ticket', (string)$ticket->id());
        if ($existing) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PET WorkProjection] Skipped: WorkItem already exists for ticket ' . $ticket->id());
            }
            return;
        }

        // 1. Determine Department
        $departmentId = $this->departmentResolver->resolveForTicket($ticket);

        // 2. Create WorkItem
        $workItemId = wp_generate_uuid4();
        
        $workItem = WorkItem::create(
            $workItemId,
            'ticket',
            (string)$ticket->id(),
            $departmentId,
            0.0, // Initial priority score
            'active',
            $event->occurredAt()
        );

        $workItem->updateAssignment(
            $ticket->queueId(),
            $ticket->ownerUserId(),
            'ticket_created'
        );

        // Populate details if available
        $due = $ticket->resolutionDueAt();
        if ($due) {
            $workItem->updateScheduling(null, $due);
            // SLA state will be calculated by SlaClockCalculator
            if ($ticket->slaSnapshotId()) {
                $workItem->updateSlaState((string)$ticket->slaSnapshotId(), null);
            }
        }

        // Commercial Info
        $customer = $this->customerRepository->findById($ticket->customerId());
        if ($customer) {
            $data = $customer->malleableData();
            $tier = isset($data['tier']) ? (int)$data['tier'] : 1;
            $workItem->updateCommercialInfo(0.0, $tier);
        }

        // Save first to ensure existence
        $this->workItemRepository->save($workItem);

        // Calculate Initial Priority Score & SLA State
        // This will update the entity and create any immediate signals
        $this->slaClockCalculator->updateItemSlaState($workItem, new \DateTimeImmutable());
        
        // Save again with updated score and SLA state
        $this->workItemRepository->save($workItem);

        if ($workItem->getAssignmentMode() === WorkItem::ASSIGNMENT_MODE_TEAM_QUEUE) {
            $queueItem = DepartmentQueue::enter(
                wp_generate_uuid4(),
                $departmentId,
                $workItemId,
                $workItem->getAssignedTeamId()
            );

            $this->departmentQueueRepository->save($queueItem);
        }
    }

    public function onTicketAssigned(TicketAssigned $event): void
    {
        if (!$this->featureFlags->isWorkProjectionEnabled()) {
            return;
        }

        $ticket = $event->ticket();
        $workItem = $this->workItemRepository->findBySource('ticket', (string)$ticket->id());
        
        if (!$workItem) {
            $departmentId = $this->departmentResolver->resolveForTicket($ticket);
            $workItemId = wp_generate_uuid4();

            $workItem = WorkItem::create(
                $workItemId,
                'ticket',
                (string)$ticket->id(),
                $departmentId,
                0.0,
                'active',
                $event->occurredAt()
            );
        }

        if ($workItem) {
            $workItem->updateDepartment($this->departmentResolver->resolveForTicket($ticket));
            $routingReason = null;
            if ($event->assignedAgentId() === null) {
                $routingReason = $event->previousOwnerUserId() !== null ? 'ticket_returned_to_queue' : 'ticket_assigned_to_team';
            } else {
                $routingReason = $event->previousOwnerUserId() !== null ? 'ticket_reassigned' : 'ticket_assigned_to_user';
            }

            $workItem->updateAssignment($ticket->queueId(), $event->assignedAgentId(), $routingReason);
            $this->workItemRepository->save($workItem);

            if ($workItem->getAssignmentMode() === WorkItem::ASSIGNMENT_MODE_TEAM_QUEUE) {
                $existingActive = $this->departmentQueueRepository->findByWorkItemId($workItem->getId());
                if ($existingActive) {
                    $existingActive->exitQueue();
                    $this->departmentQueueRepository->save($existingActive);
                }

                $queueItem = DepartmentQueue::enter(
                    wp_generate_uuid4(),
                    $workItem->getDepartmentId(),
                    $workItem->getId(),
                    $workItem->getAssignedTeamId()
                );
                $this->departmentQueueRepository->save($queueItem);
            } elseif ($workItem->getAssignmentMode() === WorkItem::ASSIGNMENT_MODE_USER_ASSIGNED) {
                $queueItem = $this->departmentQueueRepository->findByWorkItemId($workItem->getId());
                if ($queueItem && $queueItem->isUnassigned()) {
                    $queueItem->assignToUser((string)$event->assignedAgentId());
                    $this->departmentQueueRepository->save($queueItem);
                } else {
                    $queueItem = DepartmentQueue::enter(
                        wp_generate_uuid4(),
                        $workItem->getDepartmentId(),
                        $workItem->getId(),
                        $workItem->getAssignedTeamId()
                    );
                    $queueItem->assignToUser((string)$event->assignedAgentId());
                    $this->departmentQueueRepository->save($queueItem);
                }
            }
        }
    }

}
