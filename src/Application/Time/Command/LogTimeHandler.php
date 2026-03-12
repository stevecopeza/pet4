<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Support\Repository\TicketRepository;

class LogTimeHandler
{
    private TransactionManager $transactionManager;
    private TimeEntryRepository $timeEntryRepository;
    private EmployeeRepository $employeeRepository;
    private TicketRepository $ticketRepository;
    
    public function __construct(TransactionManager $transactionManager, 
        TimeEntryRepository $timeEntryRepository,
        EmployeeRepository $employeeRepository,
        TicketRepository $ticketRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->timeEntryRepository = $timeEntryRepository;
        $this->employeeRepository = $employeeRepository;
        $this->ticketRepository = $ticketRepository;
    }

    public function handle(LogTimeCommand $command): int
    {
        $entryId = 0;
        $this->transactionManager->transactional(function () use ($command, &$entryId) {
            $employee = $this->employeeRepository->findById($command->employeeId());
            if (!$employee) {
                throw new \DomainException("Employee not found: {$command->employeeId()}");
            }

            $ticket = $this->ticketRepository->findById($command->ticketId());
            if ($ticket && !$ticket->canAcceptTimeEntries()) {
                throw new \DomainException(
                    "Ticket {$command->ticketId()} cannot accept time entries. "
                    . "Time may only be logged against leaf tickets in 'in_progress' status."
                );
            }

            $timeEntry = new TimeEntry(
                $command->employeeId(),
                $command->ticketId(),
                $command->start(),
                $command->end(),
                $command->isBillable(),
                $command->description(),
                'draft',
                null,
                $command->malleableData()
            );

            $this->timeEntryRepository->save($timeEntry);
            $entryId = $timeEntry->id() ?? 0;
        });
        return $entryId;
    }
}
