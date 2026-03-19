<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

class AssignTicketToTeamCommand
{
    public function __construct(
        private int $ticketId,
        private string $teamId
    ) {
    }

    public function ticketId(): int
    {
        return $this->ticketId;
    }

    public function teamId(): string
    {
        return $this->teamId;
    }
}

