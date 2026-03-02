<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Repository\AssignmentRepository;

class EndAssignmentHandler
{
    private TransactionManager $transactionManager;
    private AssignmentRepository $assignmentRepository;

    public function __construct(TransactionManager $transactionManager, AssignmentRepository $assignmentRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->assignmentRepository = $assignmentRepository;
    }

    public function handle(EndAssignmentCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $assignment = $this->assignmentRepository->findById($command->assignmentId());

        if (!$assignment) {
            throw new \InvalidArgumentException('Assignment not found.');
        }

        if ($assignment->status() !== 'active') {
            throw new \DomainException('Assignment is not active.');
        }

        $assignment->end($command->endDate());

        $this->assignmentRepository->save($assignment);
    
        });
    }
}
