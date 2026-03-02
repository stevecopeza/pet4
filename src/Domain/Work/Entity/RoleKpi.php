<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class RoleKpi
{
    private ?int $id;
    private int $roleId;
    private int $kpiDefinitionId;
    private int $weightPercentage;
    private float $targetValue;
    private string $measurementFrequency;
    private ?\DateTimeImmutable $createdAt;

    public function __construct(
        int $roleId,
        int $kpiDefinitionId,
        int $weightPercentage,
        float $targetValue,
        string $measurementFrequency,
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->roleId = $roleId;
        $this->kpiDefinitionId = $kpiDefinitionId;
        $this->weightPercentage = $weightPercentage;
        $this->targetValue = $targetValue;
        $this->measurementFrequency = $measurementFrequency;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function kpiDefinitionId(): int
    {
        return $this->kpiDefinitionId;
    }

    public function weightPercentage(): int
    {
        return $this->weightPercentage;
    }

    public function targetValue(): float
    {
        return $this->targetValue;
    }

    public function measurementFrequency(): string
    {
        return $this->measurementFrequency;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
