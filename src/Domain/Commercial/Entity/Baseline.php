<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

use Pet\Domain\Commercial\Entity\Component\QuoteComponent;

class Baseline
{
    private ?int $id;
    private int $contractId;
    private float $totalValue;
    private float $totalInternalCost;
    private \DateTimeImmutable $createdAt;

    /**
     * @var QuoteComponent[]
     */
    private array $components;

    public function __construct(
        int $contractId,
        float $totalValue,
        float $totalInternalCost,
        array $components,
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->contractId = $contractId;
        $this->totalValue = $totalValue;
        $this->totalInternalCost = $totalInternalCost;
        $this->components = $components;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function contractId(): int
    {
        return $this->contractId;
    }

    public function totalValue(): float
    {
        return $this->totalValue;
    }

    public function totalInternalCost(): float
    {
        return $this->totalInternalCost;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return QuoteComponent[]
     */
    public function components(): array
    {
        return $this->components;
    }
}
