<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class PersonKpi
{
    private ?int $id;
    private int $employeeId;
    private int $kpiDefinitionId;
    private int $roleId;
    private \DateTimeImmutable $periodStart;
    private \DateTimeImmutable $periodEnd;
    private float $targetValue;
    private ?float $actualValue;
    private ?float $score;
    private string $status;
    private ?\DateTimeImmutable $createdAt;

    public function __construct(
        int $employeeId,
        int $kpiDefinitionId,
        int $roleId,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        float $targetValue,
        ?float $actualValue = null,
        ?float $score = null,
        string $status = 'pending',
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->employeeId = $employeeId;
        $this->kpiDefinitionId = $kpiDefinitionId;
        $this->roleId = $roleId;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->targetValue = $targetValue;
        $this->actualValue = $actualValue;
        $this->score = $score;
        $this->status = $status;
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

    public function kpiDefinitionId(): int
    {
        return $this->kpiDefinitionId;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function periodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function targetValue(): float
    {
        return $this->targetValue;
    }

    public function actualValue(): ?float
    {
        return $this->actualValue;
    }

    public function score(): ?float
    {
        return $this->score;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updateActual(float $actual, float $score): void
    {
        $this->actualValue = $actual;
        $this->score = $score;
        $this->status = 'finalized';
    }
}
