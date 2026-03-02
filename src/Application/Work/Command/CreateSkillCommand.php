<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class CreateSkillCommand
{
    private $name;
    private $capabilityId;
    private $description;

    public function __construct(string $name, int $capabilityId, string $description)
    {
        $this->name = $name;
        $this->capabilityId = $capabilityId;
        $this->description = $description;
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
