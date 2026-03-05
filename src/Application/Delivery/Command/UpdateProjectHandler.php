<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Delivery\Service\ProjectHealthTransitionEmitter;
use Pet\Domain\Delivery\Repository\ProjectRepository;

class UpdateProjectHandler
{
    private TransactionManager $transactionManager;
    private ProjectRepository $projectRepository;
    private ProjectHealthTransitionEmitter $healthEmitter;

    public function __construct(
        TransactionManager $transactionManager,
        ProjectRepository $projectRepository,
        ProjectHealthTransitionEmitter $healthEmitter
    ) {
        $this->transactionManager = $transactionManager;
        $this->projectRepository = $projectRepository;
        $this->healthEmitter = $healthEmitter;
    }

    public function handle(UpdateProjectCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $project = $this->projectRepository->findById($command->id());

        if (!$project) {
            throw new \RuntimeException('Project not found');
        }

        $project->update(
            $command->name(),
            $command->status(),
            $command->startDate(),
            $command->endDate(),
            $command->malleableData()
        );

        $this->projectRepository->save($project);

        // Evaluate health transition after project state change
        $hoursUsed = (float)($command->malleableData()['hours_used'] ?? 0);
        $this->healthEmitter->evaluate($project, $hoursUsed);
    
        });
    }
}
