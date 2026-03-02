<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Listener;

use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Application\Delivery\Command\CreateProjectCommand;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Delivery\Repository\ProjectRepository;

class CreateProjectFromQuoteListener
{
    private CreateProjectHandler $createProjectHandler;
    private ProjectRepository $projectRepository;

    public function __construct(
        CreateProjectHandler $createProjectHandler,
        ProjectRepository $projectRepository
    ) {
        $this->createProjectHandler = $createProjectHandler;
        $this->projectRepository = $projectRepository;
    }

    public function __invoke(QuoteAccepted $event): void
    {
        $quote = $event->quote();

        // Idempotency Guard: Do not create project if it already exists for this quote
        if ($this->projectRepository->findByQuoteId($quote->id())) {
            return;
        }
        
        $soldHours = 0.0;
        $implementationValue = 0.0;
        $hasImplementation = false;
        $projectTasks = [];

        foreach ($quote->components() as $component) {
            if ($component instanceof ImplementationComponent) {
                $hasImplementation = true;
                $implementationValue += $component->sellValue();
                foreach ($component->milestones() as $milestone) {
                    foreach ($milestone->tasks() as $task) {
                        $soldHours += $task->durationHours();
                        $projectTasks[] = new \Pet\Domain\Delivery\Entity\Task(
                            $task->title(),
                            $task->durationHours(),
                            false,
                            null,
                            $task->roleId()
                        );
                    }
                }
            }
        }

        if ($hasImplementation) {
            $command = new CreateProjectCommand(
                $quote->customerId(),
                'Project for Quote #' . $quote->id(), // Default name
                $soldHours,
                $quote->id(),
                $implementationValue,
                null, // startDate
                null, // endDate
                [], // malleableData
                $projectTasks
            );

            $this->createProjectHandler->handle($command);
        }
    }
}
