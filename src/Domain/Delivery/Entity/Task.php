<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Entity;

class Task
{
    private ?int $id;
    private string $name;
    private float $estimatedHours;
    private bool $completed;
    private ?int $roleId;

    public function __construct(
        string $name,
        float $estimatedHours,
        bool $completed = false,
        ?int $id = null,
        ?int $roleId = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->estimatedHours = $estimatedHours;
        $this->completed = $completed;
        $this->roleId = $roleId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function roleId(): ?int
    {
        return $this->roleId;
    }

    public function estimatedHours(): float
    {
        return $this->estimatedHours;
    }

    public function name(): string
    {
        return $this->name;
    }
    
    public function isCompleted(): bool
    {
        return $this->completed;
    }
}
