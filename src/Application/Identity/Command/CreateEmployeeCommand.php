<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

class CreateEmployeeCommand
{
    private int $wpUserId;
    private string $firstName;
    private string $lastName;
    private string $email;
    private string $status;
    private ?\DateTimeImmutable $hireDate;
    private ?int $managerId;
    private array $malleableData;
    private array $teamIds;

    public function __construct(
        int $wpUserId, 
        string $firstName, 
        string $lastName, 
        string $email, 
        string $status = 'active',
        ?\DateTimeImmutable $hireDate = null,
        ?int $managerId = null,
        array $malleableData = [],
        array $teamIds = []
    ) {
        $this->wpUserId = $wpUserId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->status = $status;
        $this->hireDate = $hireDate;
        $this->managerId = $managerId;
        $this->malleableData = $malleableData;
        $this->teamIds = $teamIds;
    }

    public function wpUserId(): int
    {
        return $this->wpUserId;
    }

    public function teamIds(): array
    {
        return $this->teamIds;
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

    public function malleableData(): array
    {
        return $this->malleableData;
    }
}
