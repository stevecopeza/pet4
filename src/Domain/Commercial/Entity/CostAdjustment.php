<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

use DateTimeImmutable;

class CostAdjustment
{
    private ?int $id;
    private int $quoteId;
    private string $description;
    private float $amount; // Positive increases cost, negative decreases cost
    private string $reason;
    private string $approvedBy;
    private DateTimeImmutable $appliedAt;

    public function __construct(
        int $quoteId,
        string $description,
        float $amount,
        string $reason,
        string $approvedBy,
        ?int $id = null,
        ?DateTimeImmutable $appliedAt = null
    ) {
        $this->quoteId = $quoteId;
        $this->description = $description;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->approvedBy = $approvedBy;
        $this->id = $id;
        $this->appliedAt = $appliedAt ?? new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
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

    public function appliedAt(): DateTimeImmutable
    {
        return $this->appliedAt;
    }
}
