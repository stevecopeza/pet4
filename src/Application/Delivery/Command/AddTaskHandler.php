<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;

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
        throw new \DomainException(
            'Legacy project task creation is disabled in tickets-only delivery execution. Use project tickets.'
        );
    }
}
