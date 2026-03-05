<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Delivery\Service\ProjectHealthTransitionEmitter;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Delivery\Event\MilestoneCompletedEvent;
use RuntimeException;

class MarkMilestoneCompleteHandler
{
    private TransactionManager $transactionManager;
    private ProjectRepository $projectRepository;
    private EventBus $eventBus;
    private ProjectHealthTransitionEmitter $healthEmitter;

    public function __construct(
        TransactionManager $transactionManager, 
        ProjectRepository $projectRepository,
        EventBus $eventBus,
        ProjectHealthTransitionEmitter $healthEmitter
    ) {
        $this->transactionManager = $transactionManager;
        $this->projectRepository = $projectRepository;
        $this->eventBus = $eventBus;
        $this->healthEmitter = $healthEmitter;
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

        // Evaluate health transition after task completion
        $hoursUsed = (float)($project->malleableData()['hours_used'] ?? 0);
        $this->healthEmitter->evaluate($project, $hoursUsed);
    
        });
    }
}
