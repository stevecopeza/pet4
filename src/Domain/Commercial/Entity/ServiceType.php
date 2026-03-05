<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class ServiceType
{
    private ?int $id;
    private string $name;
    private ?string $description;
    private string $status;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        ?string $description = null,
        string $status = 'active',
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('ServiceType name cannot be empty.');
        }
        if (!in_array($status, ['active', 'archived'], true)) {
            throw new \InvalidArgumentException("Invalid ServiceType status: {$status}");
        }

        $this->name = $name;
        $this->description = $description;
        $this->status = $status;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(string $name, ?string $description): void
    {
        if ($this->status === 'archived') {
            throw new \DomainException('Cannot update an archived service type.');
        }
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('ServiceType name cannot be empty.');
        }
        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(): void
    {
        if ($this->status === 'archived') {
            throw new \DomainException('ServiceType is already archived.');
        }
        $this->status = 'archived';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
