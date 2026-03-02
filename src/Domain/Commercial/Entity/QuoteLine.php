<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class QuoteLine
{
    private ?int $id;
    private string $description;
    private float $quantity;
    private float $unitPrice;
    private string $lineGroupType; // product, project, service

    public function __construct(
        string $description,
        float $quantity,
        float $unitPrice,
        string $lineGroupType,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->lineGroupType = $lineGroupType;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function quantity(): float
    {
        return $this->quantity;
    }

    public function unitPrice(): float
    {
        return $this->unitPrice;
    }

    public function lineGroupType(): string
    {
        return $this->lineGroupType;
    }

    public function total(): float
    {
        return $this->quantity * $this->unitPrice;
    }
}
