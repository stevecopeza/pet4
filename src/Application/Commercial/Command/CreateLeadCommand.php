<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class CreateLeadCommand
{
    private int $customerId;
    private string $subject;
    private string $description;
    private ?string $source;
    private ?float $estimatedValue;
    private array $malleableData;

    public function __construct(
        int $customerId,
        string $subject,
        string $description,
        ?string $source = null,
        ?float $estimatedValue = null,
        array $malleableData = []
    ) {
        $this->customerId = $customerId;
        $this->subject = $subject;
        $this->description = $description;
        $this->source = $source;
        $this->estimatedValue = $estimatedValue;
        $this->malleableData = $malleableData;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function estimatedValue(): ?float
    {
        return $this->estimatedValue;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }
}
