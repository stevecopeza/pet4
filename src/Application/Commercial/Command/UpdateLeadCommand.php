<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class UpdateLeadCommand
{
    private int $id;
    private string $subject;
    private string $description;
    private string $status;
    private ?string $source;
    private ?float $estimatedValue;
    private array $malleableData;

    public function __construct(
        int $id,
        string $subject,
        string $description,
        string $status,
        ?string $source,
        ?float $estimatedValue,
        array $malleableData = []
    ) {
        $this->id = $id;
        $this->subject = $subject;
        $this->description = $description;
        $this->status = $status;
        $this->source = $source;
        $this->estimatedValue = $estimatedValue;
        $this->malleableData = $malleableData;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): string
    {
        return $this->status;
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
