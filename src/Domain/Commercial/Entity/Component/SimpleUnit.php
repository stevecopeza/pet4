<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

final class SimpleUnit
{
    private ?int $id;
    private string $title;
    private ?string $description;
    private float $quantity;
    private float $unitSellPrice;
    private float $unitInternalCost;

    public function __construct(
        string $title,
        float $quantity,
        float $unitSellPrice,
        float $unitInternalCost,
        ?string $description = null,
        ?int $id = null
    ) {
        if ($quantity <= 0) {
            throw new \DomainException('Simple unit quantity must be positive.');
        }
        $this->title = $title;
        $this->quantity = $quantity;
        $this->unitSellPrice = $unitSellPrice;
        $this->unitInternalCost = $unitInternalCost;
        $this->description = $description;
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function quantity(): float
    {
        return $this->quantity;
    }

    public function unitSellPrice(): float
    {
        return $this->unitSellPrice;
    }

    public function unitInternalCost(): float
    {
        return $this->unitInternalCost;
    }

    public function sellValue(): float
    {
        return $this->quantity * $this->unitSellPrice;
    }

    public function internalCost(): float
    {
        return $this->quantity * $this->unitInternalCost;
    }
}

