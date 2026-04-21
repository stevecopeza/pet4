<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Delivery\Service\ProjectHealthTransitionEmitter;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;

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
        throw new \DomainException(
            'Legacy project milestone completion via tasks is disabled in tickets-only delivery execution.'
        );
    }
}
