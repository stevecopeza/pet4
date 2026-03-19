<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

class PullTicketCommand
{
    public function __construct(
        private int $ticketId,
        private string $requestingUserId
    ) {
    }

    public function ticketId(): int
    {
        return $this->ticketId;
    }

    public function requestingUserId(): string
    {
        return $this->requestingUserId;
    }
}

