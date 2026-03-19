<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Repository\WorkItemRepository;
use InvalidArgumentException;

class AssignWorkItemHandler
{
    private TransactionManager $transactionManager;
    public function __construct(TransactionManager $transactionManager, 
        private WorkItemRepository $repository,
    ) {
        $this->transactionManager = $transactionManager;}

    public function handle(AssignWorkItemCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $workItem = $this->repository->findById($command->workItemId());

        if (!$workItem) {
            throw new InvalidArgumentException("WorkItem not found: " . $command->workItemId());
        }

        if ($workItem->getSourceType() === 'ticket') {
            throw new InvalidArgumentException('Ticket-sourced work must be assigned via Ticket commands.');
        }

        $previousAssignedUserId = $workItem->getAssignedUserId();
        $newAssignedUserId = $command->assignedUserId();

        if ($previousAssignedUserId === $newAssignedUserId) {
            return;
        }

        $workItem->assignUser($newAssignedUserId);
        $this->repository->save($workItem);

        });
    }
}
