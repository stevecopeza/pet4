<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Entity;

class Employee
{
    private ?int $id;
    private int $wpUserId;
    private string $firstName;
    private string $lastName;
    private string $email;
    private string $status;
    private ?\DateTimeImmutable $hireDate;
    private ?int $managerId;
    private ?int $calendarId;
    private ?int $malleableSchemaVersion;
    private array $malleableData;
    private array $teamIds;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $archivedAt;

    public function __construct(
        int $wpUserId,
        string $firstName,
        string $lastName,
        string $email,
        ?int $id = null,
        string $status = 'active',
        ?\DateTimeImmutable $hireDate = null,
        ?int $managerId = null,
        ?int $calendarId = null,
        ?int $malleableSchemaVersion = null,
        array $malleableData = [],
        array $teamIds = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $archivedAt = null
    ) {
        $this->id = $id;
        $this->wpUserId = $wpUserId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->status = $status;
        $this->hireDate = $hireDate;
        $this->managerId = $managerId;
        $this->calendarId = $calendarId;
        $this->malleableSchemaVersion = $malleableSchemaVersion;
        $this->malleableData = $malleableData;
        $this->teamIds = $teamIds;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->archivedAt = $archivedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function teamIds(): array
    {
        return $this->teamIds;
    }

    public function wpUserId(): int
    {
        return $this->wpUserId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    public function email(): string
    {
        return $this->email;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function hireDate(): ?\DateTimeImmutable
    {
        return $this->hireDate;
    }

    public function managerId(): ?int
    {
        return $this->managerId;
    }

    public function calendarId(): ?int
    {
        return $this->calendarId;
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
        string $firstName, 
        string $lastName, 
        string $email, 
        string $status, 
        ?\DateTimeImmutable $hireDate, 
        ?int $managerId,
        ?int $calendarId,
        array $malleableData,
        array $teamIds
    ): void {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->status = $status;
        $this->hireDate = $hireDate;
        $this->managerId = $managerId;
        $this->calendarId = $calendarId;
        $this->malleableData = $malleableData;
        $this->teamIds = $teamIds;
    }

    public function archive(): void
    {
        $this->archivedAt = new \DateTimeImmutable();
    }
}
