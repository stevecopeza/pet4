<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

final class CapacityOverride
{
    public function __construct(
        private int $id,
        private int $employeeId,
        private \DateTimeImmutable $effectiveDate,
        private int $capacityPct,
        private ?string $reason,
        private \DateTimeImmutable $createdAt
    ) {}

    public function id(): int { return $this->id; }
    public function employeeId(): int { return $this->employeeId; }
    public function effectiveDate(): \DateTimeImmutable { return $this->effectiveDate; }
    public function capacityPct(): int { return $this->capacityPct; }
    public function reason(): ?string { return $this->reason; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
}

