<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

final class Phase
{
    private ?int $id;
    private string $name;
    private ?string $description;

    /** @var SimpleUnit[] */
    private array $units;

    public function __construct(
        string $name,
        array $units = [],
        ?string $description = null,
        ?int $id = null
    ) {
        $this->name = $name;
        $this->units = $units;
        $this->description = $description;
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * @return SimpleUnit[]
     */
    public function units(): array
    {
        return $this->units;
    }

    public function addUnit(SimpleUnit $unit): void
    {
        $this->units[] = $unit;
    }

    public function sellValue(): float
    {
        $total = 0.0;
        foreach ($this->units as $unit) {
            $total += $unit->sellValue();
        }
        return $total;
    }

    public function internalCost(): float
    {
        $total = 0.0;
        foreach ($this->units as $unit) {
            $total += $unit->internalCost();
        }
        return $total;
    }
}
