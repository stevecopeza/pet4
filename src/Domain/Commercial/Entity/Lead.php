<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class Lead
{
    private ?int $id;
    private int $customerId;
    private string $subject;
    private string $description;
    private string $status;
    private ?string $source;
    private ?float $estimatedValue;
    private ?int $malleableSchemaVersion;
    private array $malleableData;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $convertedAt;

    public function __construct(
        int $customerId,
        string $subject,
        string $description,
        string $status = 'new',
        ?string $source = null,
        ?float $estimatedValue = null,
        ?int $id = null,
        ?int $malleableSchemaVersion = null,
        array $malleableData = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $convertedAt = null
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->subject = $subject;
        $this->description = $description;
        $this->status = $status;
        $this->source = $source;
        $this->estimatedValue = $estimatedValue;
        $this->malleableSchemaVersion = $malleableSchemaVersion;
        $this->malleableData = $malleableData;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
        $this->convertedAt = $convertedAt;
    }

    public function id(): ?int
    {
        return $this->id;
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

    public function malleableSchemaVersion(): ?int
    {
        return $this->malleableSchemaVersion;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function convertedAt(): ?\DateTimeImmutable
    {
        return $this->convertedAt;
    }

    public function update(
        string $subject,
        string $description,
        string $status,
        ?string $source,
        ?float $estimatedValue,
        array $malleableData
    ): void {
        $this->subject = $subject;
        $this->description = $description;
        $this->source = $source;
        $this->estimatedValue = $estimatedValue;
        $this->malleableData = $malleableData;
        $this->updatedAt = new \DateTimeImmutable();

        // Status transition logic
        if ($status === 'converted' && $this->status !== 'converted') {
            $this->convertedAt = new \DateTimeImmutable();
        } elseif ($status !== 'converted') {
            $this->convertedAt = null;
        }
        $this->status = $status;
    }
}
