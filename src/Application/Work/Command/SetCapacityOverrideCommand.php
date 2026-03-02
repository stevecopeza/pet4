<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

final class SetCapacityOverrideCommand
{
    public function __construct(
        private int $employeeId,
        private \DateTimeImmutable $date,
        private int $capacityPct,
        private ?string $reason
    ) {}

    public function employeeId(): int { return $this->employeeId; }
    public function date(): \DateTimeImmutable { return $this->date; }
    public function capacityPct(): int { return $this->capacityPct; }
    public function reason(): ?string { return $this->reason; }
}

