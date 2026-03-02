<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class Forecast
{
    private ?int $id;
    private int $quoteId;
    private float $totalValue;
    private float $probability;
    private string $status; // 'pending', 'committed'
    private array $breakdown; // Role/Department breakdown
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $quoteId,
        float $totalValue,
        float $probability,
        string $status,
        array $breakdown = [],
        ?int $id = null
    ) {
        $this->quoteId = $quoteId;
        $this->totalValue = $totalValue;
        $this->probability = $probability;
        $this->status = $status;
        $this->breakdown = $breakdown;
        $this->id = $id;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function totalValue(): float
    {
        return $this->totalValue;
    }

    public function weightedValue(): float
    {
        return $this->totalValue * $this->probability;
    }

    public function probability(): float
    {
        return $this->probability;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function breakdown(): array
    {
        return $this->breakdown;
    }

    public function commit(): void
    {
        $this->status = 'committed';
        $this->probability = 1.0;
    }
}
