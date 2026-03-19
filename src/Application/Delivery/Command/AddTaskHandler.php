<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Delivery\Event\ProjectTaskCreated;

class AddTaskHandler
{
    private TransactionManager $transactionManager;
    private ProjectRepository $projectRepository;
    private EventBus $eventBus;

    public function __construct(TransactionManager $transactionManager, ProjectRepository $projectRepository, EventBus $eventBus)
    {
        $this->transactionManager = $transactionManager;
        $this->projectRepository = $projectRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(AddTaskCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $project = $this->projectRepository->findById($command->projectId());
        if (!$project) {
            throw new \DomainException("Project not found: {$command->projectId()}");
        }

        $task = new Task(
            $command->name(),
            $command->estimatedHours(),
            false,
            null,
            $command->roleId()
        );

        $project->addTask($task);

        $this->projectRepository->save($project);

        $this->eventBus->dispatch(new ProjectTaskCreated($project, $task));
    
        });
    }
}
