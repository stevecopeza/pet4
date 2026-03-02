<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Delivery\Event\MilestoneCompletedEvent;
use RuntimeException;

class MarkMilestoneCompleteHandler
{
    private TransactionManager $transactionManager;
    private ProjectRepository $projectRepository;
    private EventBus $eventBus;

    public function __construct(TransactionManager $transactionManager, 
        ProjectRepository $projectRepository,
        EventBus $eventBus
    ) {
        $this->transactionManager = $transactionManager;
        $this->projectRepository = $projectRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(MarkMilestoneCompleteCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $project = $this->projectRepository->findById($command->projectId());
        if (!$project) {
            throw new RuntimeException("Project not found: " . $command->projectId());
        }

        $project->completeTask($command->milestoneTitle());

        $this->projectRepository->save($project);

        $this->eventBus->dispatch(new MilestoneCompletedEvent(
            $project->id(),
            $command->milestoneTitle()
        ));
    
        });
    }
}
