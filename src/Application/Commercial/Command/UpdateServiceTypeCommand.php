<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class UpdateServiceTypeCommand
{
    private int $id;
    private string $name;
    private ?string $description;

    public function __construct(int $id, string $name, ?string $description = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
    }

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function description(): ?string { return $this->description; }
}
