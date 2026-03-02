<?php

declare(strict_types=1);

namespace Pet\Domain\Configuration\Entity;

enum SchemaStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case HISTORICAL = 'historical';
}

class SchemaDefinition
{
    private ?int $id;
    private string $entityType;
    private int $version;
    private array $schema;
    private SchemaStatus $status;
    private ?\DateTimeImmutable $publishedAt;
    private ?int $publishedByEmployeeId;
    private ?\DateTimeImmutable $createdAt;
    private ?int $createdByEmployeeId;

    public function __construct(
        string $entityType,
        int $version,
        array $schema,
        ?int $id = null,
        SchemaStatus $status = SchemaStatus::DRAFT,
        ?\DateTimeImmutable $publishedAt = null,
        ?int $publishedByEmployeeId = null,
        ?\DateTimeImmutable $createdAt = null,
        ?int $createdByEmployeeId = null
    ) {
        $this->id = $id;
        $this->entityType = $entityType;
        $this->version = $version;
        $this->schema = $schema;
        $this->status = $status;
        $this->publishedAt = $publishedAt;
        $this->publishedByEmployeeId = $publishedByEmployeeId;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->createdByEmployeeId = $createdByEmployeeId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function schema(): array
    {
        return $this->schema;
    }

    public function status(): SchemaStatus
    {
        return $this->status;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function publishedByEmployeeId(): ?int
    {
        return $this->publishedByEmployeeId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function createdByEmployeeId(): ?int
    {
        return $this->createdByEmployeeId;
    }

    // Business Logic Methods

    public function publish(int $publisherId): void
    {
        if ($this->status !== SchemaStatus::DRAFT) {
            throw new \DomainException("Only draft schemas can be published.");
        }

        $this->status = SchemaStatus::ACTIVE;
        $this->publishedAt = new \DateTimeImmutable();
        $this->publishedByEmployeeId = $publisherId;
    }

    public function markAsHistorical(): void
    {
        if ($this->status !== SchemaStatus::ACTIVE) {
            throw new \DomainException("Only active schemas can be marked as historical.");
        }

        $this->status = SchemaStatus::HISTORICAL;
    }

    public function updateSchema(array $newSchema): void
    {
        if ($this->status !== SchemaStatus::DRAFT) {
            throw new \DomainException("Only draft schemas can be modified.");
        }
        
        $this->schema = $newSchema;
    }
}
