<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

class QuoteCatalogItem
{
    private string $description;
    private float $quantity;
    private float $unitSellPrice;
    private float $unitInternalCost;
    private ?int $id;
    private ?int $catalogItemId;
    private array $wbsSnapshot;
    private string $type;
    private ?string $sku;
    private ?int $roleId;

    public function __construct(
        string $description,
        float $quantity,
        float $unitSellPrice,
        float $unitInternalCost,
        ?int $id = null,
        ?int $catalogItemId = null,
        array $wbsSnapshot = [],
        string $type = 'service', // Defaulting to service for backward compatibility, but should be explicit
        ?string $sku = null,
        ?int $roleId = null
    ) {
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitSellPrice = $unitSellPrice;
        $this->unitInternalCost = $unitInternalCost;
        $this->id = $id;
        $this->catalogItemId = $catalogItemId;
        $this->wbsSnapshot = $wbsSnapshot;
        $this->type = $type;
        $this->sku = $sku;
        $this->roleId = $roleId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function roleId(): ?int
    {
        return $this->roleId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function catalogItemId(): ?int
    {
        return $this->catalogItemId;
    }

    public function wbsSnapshot(): array
    {
        return $this->wbsSnapshot;
    }

    public function description(): string
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
