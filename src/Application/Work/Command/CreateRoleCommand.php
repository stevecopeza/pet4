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

    public function __construct(
        string $name,
        string $level,
        string $description,
        string $successCriteria,
        array $requiredSkills = []
    ) {
        $this->name = $name;
        $this->level = $level;
        $this->description = $description;
        $this->successCriteria = $successCriteria;
        $this->requiredSkills = $requiredSkills;
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
}
