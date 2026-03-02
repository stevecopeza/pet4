<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Activity\Entity\ActivityLog;
use InvalidArgumentException;

class AssignWorkItemHandler
{
    private TransactionManager $transactionManager;
    public function __construct(TransactionManager $transactionManager, 
        private WorkItemRepository $repository,
        private ActivityLogRepository $activityLogRepository
    ) {
        $this->transactionManager = $transactionManager;}

    public function handle(AssignWorkItemCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $workItem = $this->repository->findById($command->workItemId());

        if (!$workItem) {
            throw new InvalidArgumentException("WorkItem not found: " . $command->workItemId());
        }

        $previousAssignedUserId = $workItem->getAssignedUserId();
        $newAssignedUserId = $command->assignedUserId();

        if ($previousAssignedUserId === $newAssignedUserId) {
            return;
        }

        $workItem->assignUser($newAssignedUserId);
        $this->repository->save($workItem);

        if ($workItem->getSourceType() === 'ticket') {
            $ticketId = (int) $workItem->getSourceId();
            $currentUserId = function_exists('get_current_user_id') ? get_current_user_id() : 0;
            $actorId = $currentUserId > 0 ? (int) $currentUserId : null;

            $description = sprintf(
                'Ticket assignment changed to user %s%s',
                $newAssignedUserId,
                $previousAssignedUserId !== null && $previousAssignedUserId !== ''
                    ? sprintf(' (previously %s)', $previousAssignedUserId)
                    : ''
            );

            $log = new ActivityLog(
                'ticket_assignment_changed',
                $description,
                $actorId,
                'ticket',
                $ticketId
            );

            $this->activityLogRepository->save($log);
        }
    
        });
    }
}
