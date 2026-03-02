<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Entity;

class Contact
{
    private ?int $id;
    private string $firstName;
    private string $lastName;
    private string $email;
    private ?string $phone;
    private ?int $malleableSchemaVersion;
    private array $malleableData;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $archivedAt;
    
    /** @var ContactAffiliation[] */
    private array $affiliations;

    public function __construct(
        string $firstName,
        string $lastName,
        string $email,
        ?string $phone = null,
        array $affiliations = [],
        ?int $id = null,
        ?int $malleableSchemaVersion = null,
        array $malleableData = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $archivedAt = null
    ) {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phone = $phone;
        $this->affiliations = $affiliations;
        $this->malleableSchemaVersion = $malleableSchemaVersion;
        $this->malleableData = $malleableData;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->archivedAt = $archivedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * @deprecated Use affiliations() instead. This returns the first customer ID for backward compatibility.
     */
    public function customerId(): int
    {
        if (empty($this->affiliations)) {
            return 0;
        }
        return $this->affiliations[0]->customerId();
    }

    /**
     * @deprecated Use affiliations() instead.
     */
    public function siteId(): ?int
    {
        if (empty($this->affiliations)) {
            return null;
        }
        return $this->affiliations[0]->siteId();
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    /**
     * @return ContactAffiliation[]
     */
    public function affiliations(): array
    {
        return $this->affiliations;
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

    public function addAffiliation(ContactAffiliation $affiliation): void
    {
        $this->affiliations[] = $affiliation;
    }

    public function update(
        string $firstName,
        string $lastName,
        string $email,
        ?string $phone,
        array $affiliations,
        array $malleableData
    ): void {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phone = $phone;
        $this->affiliations = $affiliations;
        $this->malleableData = $malleableData;
    }

    public function archive(): void
    {
        $this->archivedAt = new \DateTimeImmutable();
    }
}
