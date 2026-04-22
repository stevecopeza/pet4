<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Repository\TeamRepository;

class ApproveQuoteHandler
{
    public function __construct(
        private QuoteRepository  $quoteRepository,
        private TeamRepository   $teamRepository,
        private EmployeeRepository $employeeRepository
    ) {}

    public function handle(ApproveQuoteCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->quoteId);

        if (!$quote) {
            throw new \DomainException("Quote #{$command->quoteId} not found.");
        }

        // Resolve the WP user ID to an employee record.
        // An employee record is required to record the approver ID on the quote.
        $employee = $this->employeeRepository->findByWpUserId($command->approverUserId);
        if (!$employee) {
            throw new \DomainException('Approver does not have an employee record.');
        }
        $employeeId = $employee->id();

        // Check 1: is the approver a team manager or escalation manager?
        $isManager = false;
        $teams = $this->teamRepository->findAll();
        foreach ($teams as $team) {
            if ($team->managerId() === $employeeId ||
                $team->escalationManagerId() === $employeeId) {
                $isManager = true;
                break;
            }
        }

        // Check 2: is the approver the quote's creator (self-approval)?
        // A sales person may approve their own quote without requiring a separate manager.
        // createdByUserId is a WP user ID; $command->approverUserId is also a WP user ID.
        $isCreator = ($quote->createdByUserId() !== null
            && $quote->createdByUserId() === $command->approverUserId);

        if (!$isManager && !$isCreator) {
            throw new \DomainException(
                'Only the quote creator or a team manager can approve quotes.'
            );
        }

        $quote->approve($employeeId);
        $this->quoteRepository->save($quote);
    }
}
