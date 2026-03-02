<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class AssignKpiToRoleCommand
{
    private int $roleId;
    private int $kpiDefinitionId;
    private int $weightPercentage;
    private float $targetValue;
    private string $measurementFrequency;

    public function __construct(
        int $roleId,
        int $kpiDefinitionId,
        int $weightPercentage,
        float $targetValue,
        string $measurementFrequency
    ) {
        $this->roleId = $roleId;
        $this->kpiDefinitionId = $kpiDefinitionId;
        $this->weightPercentage = $weightPercentage;
        $this->targetValue = $targetValue;
        $this->measurementFrequency = $measurementFrequency;
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
}
