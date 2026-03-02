<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class CreateCatalogItemCommand
{
    private string $name;
    private float $unitPrice;
    private float $unitCost;
    private ?string $sku;
    private ?string $description;
    private ?string $category;
    private string $type;
    private array $wbsTemplate;

    public function __construct(
        string $name,
        float $unitPrice,
        float $unitCost,
        ?string $sku = null,
        ?string $description = null,
        ?string $category = null,
        string $type = 'product',
        array $wbsTemplate = []
    ) {
        $this->name = $name;
        $this->unitPrice = $unitPrice;
        $this->unitCost = $unitCost;
        $this->sku = $sku;
        $this->description = $description;
        $this->category = $category;
        $this->type = $type;
        $this->wbsTemplate = $wbsTemplate;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function unitPrice(): float
    {
        return $this->unitPrice;
    }

    public function unitCost(): float
    {
        return $this->unitCost;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function wbsTemplate(): array
    {
        return $this->wbsTemplate;
    }
}
