<?php

declare(strict_types=1);

namespace Pet\Application\Team\Command;

class CreateTeamCommand
{
    private string $name;
    private ?int $parentTeamId;
    private ?int $managerId;
    private ?int $escalationManagerId;
    private string $status;
    private ?string $visualType;
    private ?string $visualRef;
    private array $memberIds;

    public function __construct(
        string $name,
        ?int $parentTeamId = null,
        ?int $managerId = null,
        ?int $escalationManagerId = null,
        string $status = 'active',
        ?string $visualType = null,
        ?string $visualRef = null,
        array $memberIds = []
    ) {
        $this->name = $name;
        $this->parentTeamId = $parentTeamId;
        $this->managerId = $managerId;
        $this->escalationManagerId = $escalationManagerId;
        $this->status = $status;
        $this->visualType = $visualType;
        $this->visualRef = $visualRef;
        $this->memberIds = $memberIds;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function memberIds(): array
    {
        return $this->memberIds;
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
}
