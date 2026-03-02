<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

class RecurringServiceComponent extends QuoteComponent
{
    private string $serviceName;
    private array $slaSnapshot;
    private string $cadence; // 'monthly', 'quarterly', 'annually'
    private int $termMonths;
    private string $renewalModel; // 'auto', 'manual'
    private float $sellPricePerPeriod;
    private float $internalCostPerPeriod;

    public function __construct(
        string $serviceName,
        array $slaSnapshot,
        string $cadence,
        int $termMonths,
        string $renewalModel,
        float $sellPricePerPeriod,
        float $internalCostPerPeriod,
        ?string $description = null,
        ?int $id = null,
        string $section = 'General'
    ) {
        parent::__construct('recurring', $description, $id, $section);
        $this->serviceName = $serviceName;
        $this->slaSnapshot = $slaSnapshot;
        $this->cadence = $cadence;
        $this->termMonths = $termMonths;
        $this->renewalModel = $renewalModel;
        $this->sellPricePerPeriod = $sellPricePerPeriod;
        $this->internalCostPerPeriod = $internalCostPerPeriod;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    public function slaSnapshot(): array
    {
        return $this->slaSnapshot;
    }

    public function cadence(): string
    {
        return $this->cadence;
    }

    public function termMonths(): int
    {
        return $this->termMonths;
    }

    public function renewalModel(): string
    {
        return $this->renewalModel;
    }

    public function sellPricePerPeriod(): float
    {
        return $this->sellPricePerPeriod;
    }

    public function internalCostPerPeriod(): float
    {
        return $this->internalCostPerPeriod;
    }

    private function calculatePeriods(): float
    {
        $monthsPerPeriod = match ($this->cadence) {
            'monthly' => 1,
            'quarterly' => 3,
            'annually' => 12,
            default => 1,
        };
        
        return $this->termMonths / $monthsPerPeriod;
    }

    public function sellValue(): float
    {
        return $this->sellPricePerPeriod * $this->calculatePeriods();
    }

    public function internalCost(): float
    {
        return $this->internalCostPerPeriod * $this->calculatePeriods();
    }
}
