<?php

declare(strict_types=1);

namespace Pet\Domain\Team\Entity;

class Team
{
    private ?int $id;
    private string $name;
    private ?int $parentTeamId;
    private ?int $managerId;
    private ?int $escalationManagerId;
    private string $status;
    private ?string $visualType;
    private ?string $visualRef;
    private int $visualVersion;
    private ?\DateTimeImmutable $visualUpdatedAt;
    private array $memberIds;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $archivedAt;

    public function __construct(
        string $name,
        ?int $id = null,
        ?int $parentTeamId = null,
        ?int $managerId = null,
        ?int $escalationManagerId = null,
        string $status = 'active',
        ?string $visualType = null,
        ?string $visualRef = null,
        int $visualVersion = 1,
        ?\DateTimeImmutable $visualUpdatedAt = null,
        array $memberIds = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $archivedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->parentTeamId = $parentTeamId;
        $this->managerId = $managerId;
        $this->escalationManagerId = $escalationManagerId;
        $this->status = $status;
        $this->visualType = $visualType;
        $this->visualRef = $visualRef;
        $this->visualVersion = $visualVersion;
        $this->visualUpdatedAt = $visualUpdatedAt;
        $this->memberIds = $memberIds;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->archivedAt = $archivedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function memberIds(): array
    {
        return $this->memberIds;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function parentTeamId(): ?int
    {
        return $this->parentTeamId;
    }

    public function managerId(): ?int
    {
        return $this->managerId;
    }

    public function escalationManagerId(): ?int
    {
        return $this->escalationManagerId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function visualType(): ?string
    {
        return $this->visualType;
    }

    public function visualRef(): ?string
    {
        return $this->visualRef;
    }

    public function visualVersion(): int
    {
        return $this->visualVersion;
    }

    public function visualUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->visualUpdatedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->archivedAt === null;
    }
}
