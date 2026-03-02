<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

final class DecideLeaveRequestCommand
{
    public function __construct(
        private int $requestId,
        private int $decidedByEmployeeId,
        private string $decision, // approved|rejected|cancelled
        private ?string $reason
    ) {}

    public function requestId(): int { return $this->requestId; }
    public function decidedByEmployeeId(): int { return $this->decidedByEmployeeId; }
    public function decision(): string { return $this->decision; }
    public function reason(): ?string { return $this->reason; }
}

