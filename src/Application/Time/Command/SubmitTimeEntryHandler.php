<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Event\EventBus;
use Pet\Application\System\Service\TouchedTracker;

final class SubmitTimeEntryHandler
{
    private TransactionManager $transactionManager;
    public function __construct(TransactionManager $transactionManager, 
        private TimeEntryRepository $timeEntryRepository,
        private EventBus $eventBus,
        private TouchedTracker $touched
    ) {
        $this->transactionManager = $transactionManager;
    }

    public function handle(SubmitTimeEntryCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $entry = $this->timeEntryRepository->findById($command->timeEntryId());
        if (!$entry) {
            throw new \DomainException('Time entry not found');
        }
        $entry->submit();
        $this->timeEntryRepository->save($entry);

        foreach ($entry->releaseEvents() as $event) {
            $this->eventBus->dispatch($event);
        }

        $this->touched->mark('time_entry', (int)$entry->id(), $entry->employeeId());
    
        });
    }
}
