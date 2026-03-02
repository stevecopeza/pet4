<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class UpdateSkillCommand
{
    private int $id;
    private string $name;
    private int $capabilityId;
    private string $description;

    public function __construct(int $id, string $name, int $capabilityId, string $description)
    {
        $this->id = $id;
        $this->name = $name;
        $this->capabilityId = $capabilityId;
        $this->description = $description;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function capabilityId(): int
    {
        return $this->capabilityId;
    }

    public function description(): string
    {
        return $this->description;
    }
}

