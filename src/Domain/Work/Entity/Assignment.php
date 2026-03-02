<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class Assignment
{
    private ?int $id;
    private int $employeeId;
    private int $roleId;
    private \DateTimeImmutable $startDate;
    private ?\DateTimeImmutable $endDate;
    private int $allocationPct;
    private string $status;
    private ?\DateTimeImmutable $createdAt;

    public function __construct(
        int $employeeId,
        int $roleId,
        \DateTimeImmutable $startDate,
        ?int $id = null,
        ?\DateTimeImmutable $endDate = null,
        int $allocationPct = 100,
        string $status = 'active',
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->employeeId = $employeeId;
        $this->roleId = $roleId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->allocationPct = $allocationPct;
        $this->status = $status;
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

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function startDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function allocationPct(): int
    {
        return $this->allocationPct;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function end(\DateTimeImmutable $endDate): void
    {
        $this->endDate = $endDate;
        $this->status = 'completed';
    }
}
