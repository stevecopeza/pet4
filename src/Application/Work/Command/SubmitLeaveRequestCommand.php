<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

final class SubmitLeaveRequestCommand
{
    public function __construct(
        private int $employeeId,
        private int $leaveTypeId,
        private \DateTimeImmutable $startDate,
        private \DateTimeImmutable $endDate,
        private ?string $notes
    ) {}

    public function employeeId(): int { return $this->employeeId; }
    public function leaveTypeId(): int { return $this->leaveTypeId; }
    public function startDate(): \DateTimeImmutable { return $this->startDate; }
    public function endDate(): \DateTimeImmutable { return $this->endDate; }
    public function notes(): ?string { return $this->notes; }
}

