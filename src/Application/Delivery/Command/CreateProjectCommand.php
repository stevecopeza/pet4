<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

class CreateProjectCommand
{
    private int $customerId;
    private string $name;
    private float $soldHours;
    private ?int $sourceQuoteId;
    private float $soldValue;
    private ?\DateTimeImmutable $startDate;
    private ?\DateTimeImmutable $endDate;
    private array $malleableData;
    private array $tasks;

    public function __construct(
        int $customerId,
        string $name,
        float $soldHours,
        ?int $sourceQuoteId = null,
        float $soldValue = 0.00,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
        array $malleableData = [],
        array $tasks = []
    ) {
        $this->customerId = $customerId;
        $this->name = $name;
        $this->soldHours = $soldHours;
        $this->sourceQuoteId = $sourceQuoteId;
        $this->soldValue = $soldValue;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->malleableData = $malleableData;
        $this->tasks = $tasks;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function soldHours(): float
    {
        return $this->soldHours;
    }

    public function sourceQuoteId(): ?int
    {
        return $this->sourceQuoteId;
    }

    public function soldValue(): float
    {
        return $this->soldValue;
    }

    public function startDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }

    public function tasks(): array
    {
        return $this->tasks;
    }
}
