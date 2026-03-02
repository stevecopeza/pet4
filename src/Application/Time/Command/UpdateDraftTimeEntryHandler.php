<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Time\Repository\TimeEntryRepository;

class UpdateDraftTimeEntryHandler
{
    private TransactionManager $transactionManager;
    private TimeEntryRepository $timeEntryRepository;

    public function __construct(
        TransactionManager $transactionManager,
        TimeEntryRepository $timeEntryRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->timeEntryRepository = $timeEntryRepository;
    }

    public function handle(UpdateDraftTimeEntryCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            $entry = $this->timeEntryRepository->findById($command->timeEntryId());
            if (!$entry) {
                throw new \DomainException("Time entry not found: {$command->timeEntryId()}");
            }

            // Domain method guards: only drafts can be edited
            $entry->updateDraft(
                $command->description(),
                $command->start(),
                $command->end(),
                $command->isBillable()
            );

            $this->timeEntryRepository->save($entry);
        });
    }
}
