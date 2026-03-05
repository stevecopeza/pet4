<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class CreateRoleCommand
{
    private string $name;
    private string $level;
    private string $description;
    private string $successCriteria;
    private array $requiredSkills;
    private ?float $baseInternalRate;

    public function __construct(
        string $name,
        string $level,
        string $description,
        string $successCriteria,
        array $requiredSkills = [],
        ?float $baseInternalRate = null
    ) {
        $this->name = $name;
        $this->level = $level;
        $this->description = $description;
        $this->successCriteria = $successCriteria;
        $this->requiredSkills = $requiredSkills;
        $this->baseInternalRate = $baseInternalRate;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function level(): string
    {
        return $this->level;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function successCriteria(): string
    {
        return $this->successCriteria;
    }

    public function requiredSkills(): array
    {
        return $this->requiredSkills;
    }

    public function baseInternalRate(): ?float
    {
        return $this->baseInternalRate;
    }
}
