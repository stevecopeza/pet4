<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class AddQuoteLineCommand
{
    private int $quoteId;
    private string $description;
    private float $quantity;
    private float $unitPrice;
    private string $lineGroupType;

    public function __construct(
        int $quoteId,
        string $description,
        float $quantity,
        float $unitPrice,
        string $lineGroupType
    ) {
        $this->quoteId = $quoteId;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->lineGroupType = $lineGroupType;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
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
}
