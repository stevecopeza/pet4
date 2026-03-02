<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class PersonSkill
{
    private ?int $id;
    private int $employeeId;
    private int $skillId;
    private ?int $reviewCycleId;
    private int $selfRating;
    private int $managerRating;
    private \DateTimeImmutable $effectiveDate;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $employeeId,
        int $skillId,
        int $selfRating,
        int $managerRating,
        \DateTimeImmutable $effectiveDate,
        ?int $reviewCycleId = null,
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->employeeId = $employeeId;
        $this->skillId = $skillId;
        $this->selfRating = $selfRating;
        $this->managerRating = $managerRating;
        $this->effectiveDate = $effectiveDate;
        $this->reviewCycleId = $reviewCycleId;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function skillId(): int
    {
        return $this->skillId;
    }

    public function reviewCycleId(): ?int
    {
        return $this->reviewCycleId;
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

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Methods to update ratings could be added if mutable, but typically ratings are snapshots.
    // However, for corrections, we might allow update.
    public function updateRatings(int $selfRating, int $managerRating): void
    {
        $this->selfRating = $selfRating;
        $this->managerRating = $managerRating;
    }
}
