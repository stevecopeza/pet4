<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class UpdateCatalogProductCommand
{
    private int $id;
    private string $name;
    private float $unitPrice;
    private float $unitCost;
    private ?string $description;
    private ?string $category;

    public function __construct(int $id, string $name, float $unitPrice, float $unitCost, ?string $description = null, ?string $category = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->unitPrice = $unitPrice;
        $this->unitCost = $unitCost;
        $this->description = $description;
        $this->category = $category;
    }

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function unitPrice(): float { return $this->unitPrice; }
    public function unitCost(): float { return $this->unitCost; }
    public function description(): ?string { return $this->description; }
    public function category(): ?string { return $this->category; }
}
