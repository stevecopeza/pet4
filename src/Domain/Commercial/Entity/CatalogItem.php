<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class CatalogItem
{
    private ?int $id;
    private ?string $sku;
    private string $type;
    private string $name;
    private ?string $description;
    private ?string $category;
    private float $unitPrice;
    private float $unitCost;
    private array $wbsTemplate;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        float $unitPrice,
        float $unitCost,
        string $type = 'product',
        ?string $sku = null,
        ?string $description = null,
        ?string $category = null,
        array $wbsTemplate = [],
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->name = $name;
        $this->unitPrice = $unitPrice;
        $this->unitCost = $unitCost;
        $this->type = $type;
        $this->sku = $sku;
        $this->description = $description;
        $this->category = $category;
        $this->wbsTemplate = $wbsTemplate;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();

        $this->validate();
    }

    private function validate(): void
    {
        if ($this->type === 'service') {
            if ($this->unitCost <= 0) {
                throw new \InvalidArgumentException('Service catalog items must have a valid internal cost greater than 0.');
            }
            if ($this->unitPrice <= 0) {
                throw new \InvalidArgumentException('Service catalog items must have a valid sell price greater than 0.');
            }
        }
        
        if (!in_array($this->type, ['product', 'service'], true)) {
             throw new \InvalidArgumentException("Invalid catalog item type: {$this->type}. Must be 'product' or 'service'.");
        }
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function wbsTemplate(): array
    {
        return $this->wbsTemplate;
    }

    public function unitPrice(): float
    {
        return $this->unitPrice;
    }

    public function unitCost(): float
    {
        return $this->unitCost;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
