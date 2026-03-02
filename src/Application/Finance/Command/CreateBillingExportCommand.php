<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

final class CreateBillingExportCommand
{
    private int $customerId;
    private \DateTimeImmutable $periodStart;
    private \DateTimeImmutable $periodEnd;
    private int $createdByEmployeeId;

    public function __construct(
        int $customerId,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        int $createdByEmployeeId
    ) {
        $this->customerId = $customerId;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->createdByEmployeeId = $createdByEmployeeId;
    }

    public function customerId(): int { return $this->customerId; }
    public function periodStart(): \DateTimeImmutable { return $this->periodStart; }
    public function periodEnd(): \DateTimeImmutable { return $this->periodEnd; }
    public function createdByEmployeeId(): int { return $this->createdByEmployeeId; }
}
