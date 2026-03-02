<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class RateEmployeeSkillCommand
{
    private int $employeeId;
    private int $skillId;
    private int $selfRating;
    private int $managerRating;
    private \DateTimeImmutable $effectiveDate;
    private ?int $reviewCycleId;

    public function __construct(
        int $employeeId,
        int $skillId,
        int $selfRating,
        int $managerRating,
        string $effectiveDate = 'now',
        ?int $reviewCycleId = null
    ) {
        $this->employeeId = $employeeId;
        $this->skillId = $skillId;
        $this->selfRating = $selfRating;
        $this->managerRating = $managerRating;
        $this->effectiveDate = new \DateTimeImmutable($effectiveDate);
        $this->reviewCycleId = $reviewCycleId;
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function skillId(): int
    {
        return $this->skillId;
    }

    public function selfRating(): int
    {
        return $this->selfRating;
    }

    public function managerRating(): int
    {
        return $this->managerRating;
    }

    public function effectiveDate(): \DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    public function reviewCycleId(): ?int
    {
        return $this->reviewCycleId;
    }
}
