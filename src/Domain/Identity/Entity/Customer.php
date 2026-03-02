<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Entity;

class Customer
{
    private ?int $id;
    private string $name;
    private ?string $legalName;
    private string $contactEmail;
    private string $status;
    private ?int $malleableSchemaVersion;
    private array $malleableData;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $archivedAt;

    public function __construct(
        string $name,
        string $contactEmail,
        ?int $id = null,
        ?string $legalName = null,
        string $status = 'active',
        ?int $malleableSchemaVersion = null,
        array $malleableData = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $archivedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->legalName = $legalName;
        $this->contactEmail = $contactEmail;
        $this->status = $status;
        $this->malleableSchemaVersion = $malleableSchemaVersion;
        $this->malleableData = $malleableData;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->archivedAt = $archivedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function legalName(): ?string
    {
        return $this->legalName;
    }

    public function contactEmail(): string
    {
        return $this->contactEmail;
    }

    public function status(): string
    {
        return $this->status;
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

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function update(
        string $name, 
        string $contactEmail, 
        ?string $legalName, 
        string $status, 
        array $malleableData
    ): void {
        $this->name = $name;
        $this->contactEmail = $contactEmail;
        $this->legalName = $legalName;
        $this->status = $status;
        $this->malleableData = $malleableData;
    }

    public function archive(): void
    {
        $this->archivedAt = new \DateTimeImmutable();
    }
}
