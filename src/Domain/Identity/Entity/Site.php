<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Entity;

class Site
{
    private ?int $id;
    private int $customerId;
    private string $name;
    private ?string $addressLines;
    private ?string $city;
    private ?string $state;
    private ?string $postalCode;
    private ?string $country;
    private string $status;
    private ?int $malleableSchemaVersion;
    private array $malleableData;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $archivedAt;

    public function __construct(
        int $customerId,
        string $name,
        ?string $addressLines = null,
        ?string $city = null,
        ?string $state = null,
        ?string $postalCode = null,
        ?string $country = null,
        string $status = 'active',
        ?int $id = null,
        ?int $malleableSchemaVersion = null,
        array $malleableData = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $archivedAt = null
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->name = $name;
        $this->addressLines = $addressLines;
        $this->city = $city;
        $this->state = $state;
        $this->postalCode = $postalCode;
        $this->country = $country;
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

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function addressLines(): ?string
    {
        return $this->addressLines;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function country(): ?string
    {
        return $this->country;
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
}
