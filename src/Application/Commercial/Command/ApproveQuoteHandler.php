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

        // Resolve the WP user ID to an employee ID — team.manager_id stores employee IDs.
        $employee = $this->employeeRepository->findByWpUserId($command->approverUserId);
        if (!$employee) {
            throw new \DomainException('Approver does not have an employee record.');
        }
        $employeeId = $employee->id();

        // Verify the approver is actually a manager or escalation manager of some team
        $teams = $this->teamRepository->findAll();
        $isAuthorised = false;

        foreach ($teams as $team) {
            if ($team->managerId() === $employeeId ||
                $team->escalationManagerId() === $employeeId) {
                $isAuthorised = true;
                break;
            }
        }

        if (!$isAuthorised) {
            throw new \DomainException('Only a team manager or escalation manager can approve quotes.');
        }

        $quote->approve($employeeId);
        $this->quoteRepository->save($quote);
    }
}
