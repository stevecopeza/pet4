<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class UpdateRoleCommand
{
    private $id;
    private $name;
    private $level;
    private $description;
    private $successCriteria;
    private $requiredSkills;
    private ?float $baseInternalRate;

    public function __construct(
        int $id,
        string $name,
        string $level,
        string $description,
        string $successCriteria,
        array $requiredSkills,
        ?float $baseInternalRate = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->level = $level;
        $this->description = $description;
        $this->successCriteria = $successCriteria;
        $this->requiredSkills = $requiredSkills;
        $this->baseInternalRate = $baseInternalRate;
    }

    public function id(): int
    {
        return $this->id;
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
