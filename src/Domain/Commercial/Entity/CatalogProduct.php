<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class CatalogProduct
{
    private ?int $id;
    private string $sku;
    private string $name;
    private ?string $description;
    private ?string $category;
    private float $unitPrice;
    private float $unitCost;
    private string $status;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $sku,
        string $name,
        float $unitPrice,
        float $unitCost,
        ?string $description = null,
        ?string $category = null,
        string $status = 'active',
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        if ($unitPrice < 0) {
            throw new \InvalidArgumentException('CatalogProduct unit price cannot be negative.');
        }
        if ($unitCost < 0) {
            throw new \InvalidArgumentException('CatalogProduct unit cost cannot be negative.');
        }
        if (empty(trim($sku))) {
            throw new \InvalidArgumentException('CatalogProduct SKU cannot be empty.');
        }
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('CatalogProduct name cannot be empty.');
        }
        if (!in_array($status, ['active', 'archived'], true)) {
            throw new \InvalidArgumentException("Invalid CatalogProduct status: {$status}");
        }

        $this->sku = $sku;
        $this->name = $name;
        $this->unitPrice = $unitPrice;
        $this->unitCost = $unitCost;
        $this->description = $description;
        $this->category = $category;
        $this->status = $status;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sku(): string
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

    public function unitPrice(): float
    {
        return $this->unitPrice;
    }

    public function unitCost(): float
    {
        return $this->unitCost;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        string $name,
        float $unitPrice,
        float $unitCost,
        ?string $description,
        ?string $category
    ): void {
        if ($this->status === 'archived') {
            throw new \DomainException('Cannot update an archived catalog product.');
        }
        if ($unitPrice < 0) {
            throw new \InvalidArgumentException('CatalogProduct unit price cannot be negative.');
        }
        if ($unitCost < 0) {
            throw new \InvalidArgumentException('CatalogProduct unit cost cannot be negative.');
        }
        $this->name = $name;
        $this->unitPrice = $unitPrice;
        $this->unitCost = $unitCost;
        $this->description = $description;
        $this->category = $category;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(): void
    {
        if ($this->status === 'archived') {
            throw new \DomainException('CatalogProduct is already archived.');
        }
        $this->status = 'archived';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
