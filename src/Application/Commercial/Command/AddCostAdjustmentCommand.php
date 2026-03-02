<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class AddCostAdjustmentCommand
{
    private int $quoteId;
    private string $description;
    private float $amount;
    private string $reason;
    private string $approvedBy;

    public function __construct(
        int $quoteId,
        string $description,
        float $amount,
        string $reason,
        string $approvedBy
    ) {
        $this->quoteId = $quoteId;
        $this->description = $description;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->approvedBy = $approvedBy;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function approvedBy(): string
    {
        return $this->approvedBy;
    }
}
