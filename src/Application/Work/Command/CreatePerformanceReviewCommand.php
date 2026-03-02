<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class CreatePerformanceReviewCommand
{
    private int $employeeId;
    private int $reviewerId;
    private \DateTimeImmutable $periodStart;
    private \DateTimeImmutable $periodEnd;

    public function __construct(
        int $employeeId,
        int $reviewerId,
        string $periodStart,
        string $periodEnd
    ) {
        $this->employeeId = $employeeId;
        $this->reviewerId = $reviewerId;
        $this->periodStart = new \DateTimeImmutable($periodStart);
        $this->periodEnd = new \DateTimeImmutable($periodEnd);
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function reviewerId(): int
    {
        return $this->reviewerId;
    }

    public function periodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }
}
