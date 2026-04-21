<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Event\TicketCreated;

/**
 * Creates a project ticket from accepted quote delivery components.
 * Sets correct backbone defaults: status=planned, lifecycle_owner=project,
 * is_baseline_locked=1, billing_context_type=project.
 *
 * The repository handles the post-insert root_ticket_id = id update.
 */
class CreateProjectTicketHandler
{
    private TransactionManager $transactionManager;
    private TicketRepository $ticketRepository;
    private EventBus $eventBus;

    public function __construct(
        TransactionManager $transactionManager,
        TicketRepository $ticketRepository,
        EventBus $eventBus
    ) {
        $this->transactionManager = $transactionManager;
        $this->ticketRepository = $ticketRepository;
        $this->eventBus = $eventBus;
    }

    /**
     * @return int The ID of the created ticket
     */
    public function handle(CreateProjectTicketCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command) {
            if ($command->projectId() === null || $command->projectId() <= 0) {
                throw new \DomainException('Project ticket provisioning requires a valid project_id.');
            }

            if ($command->sourceComponentId() <= 0) {
                throw new \DomainException('Project ticket provisioning requires a valid source_component_id.');
            }

            $existing = $this->ticketRepository->findByProvisioningKey(
                (int)$command->projectId(),
                $command->sourceComponentId(),
                $command->parentTicketId()
            );
            if ($existing && $existing->id() !== null) {
                return (int)$existing->id();
            }
            $ticket = new Ticket(
                $command->customerId(),
                $command->subject(),
                $command->description(),
                'planned',                              // status — project lifecycle initial
                'medium',                               // priority
                null,                                   // siteId
                null,                                   // slaId
                null,                                   // id (auto-increment)
                null,                                   // malleableSchemaVersion
                [                                       // malleableData — quote traceability only
                    'source' => 'quote',
                    'quote_id' => $command->quoteId(),
                ],
                null,                                   // createdAt
                null,                                   // openedAt
                null,                                   // closedAt
                null,                                   // resolvedAt
                null,                                   // slaSnapshotId
                null,                                   // responseDueAt
                null,                                   // resolutionDueAt
                null,                                   // respondedAt
                null,                                   // queueId
                null,                                   // ownerUserId
                null,                                   // category
                null,                                   // subcategory
                'quote',                                // intakeSource
                null,                                   // contactId
                // Backbone fields
                'project',                              // primaryContainer
                $command->projectId(),                  // projectId
                $command->quoteId(),                    // quoteId
                $command->phaseId(),                    // phaseId
                $command->parentTicketId(),             // parentTicketId
                null,                                   // rootTicketId (set by repo post-insert)
                'work',                                 // ticketKind
                $command->departmentIdExt(),            // departmentIdExt
                $command->requiredRoleId(),             // requiredRoleId
                null,                                   // skillLevel
                'project',                              // billingContextType
                null,                                   // agreementId
                null,                                   // ratePlanId
                true,                                   // isBillableDefault
                $command->soldMinutes(),                // soldMinutes
                $command->estimatedMinutes(),           // estimatedMinutes
                null,                                   // remainingMinutes (not stored)
                $command->isRollup(),                   // isRollup
                'project',                              // lifecycleOwner
                true,                                   // isBaselineLocked
                $command->changeOrderSourceTicketId(),  // changeOrderSourceTicketId
                $command->soldValueCents(),             // soldValueCents
                $command->sourceType(),                 // sourceType
                $command->sourceComponentId()           // sourceComponentId
            );

            try {
                $this->ticketRepository->save($ticket);
            } catch (\RuntimeException $e) {
                if (stripos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }

                $existing = $this->ticketRepository->findByProvisioningKey(
                    (int)$command->projectId(),
                    $command->sourceComponentId(),
                    $command->parentTicketId()
                );
                if ($existing && $existing->id() !== null) {
                    return (int)$existing->id();
                }
                throw $e;
            }

            $this->eventBus->dispatch(new TicketCreated($ticket));

            return (int)$ticket->id();
        });
    }
}
