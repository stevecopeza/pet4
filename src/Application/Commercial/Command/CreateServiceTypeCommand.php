<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class CreateServiceTypeCommand
{
    private string $name;
    private ?string $description;

    public function __construct(string $name, ?string $description = null)
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function name(): string { return $this->name; }
    public function description(): ?string { return $this->description; }
}
