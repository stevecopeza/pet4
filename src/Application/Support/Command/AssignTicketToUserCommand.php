<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

class AssignTicketToUserCommand
{
    public function __construct(
        private int $ticketId,
        private string $userId
    ) {
    }

    public function ticketId(): int
    {
        return $this->ticketId;
    }

    public function userId(): string
    {
        return $this->userId;
    }
}

