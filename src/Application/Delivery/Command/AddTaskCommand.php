<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

class AddTaskCommand
{
    private int $projectId;
    private string $name;
    private float $estimatedHours;
    private ?int $roleId;

    public function __construct(int $projectId, string $name, float $estimatedHours, ?int $roleId = null)
    {
        $this->projectId = $projectId;
        $this->name = $name;
        $this->estimatedHours = $estimatedHours;
        $this->roleId = $roleId;
    }

    public function projectId(): int
    {
        return $this->projectId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function estimatedHours(): float
    {
        return $this->estimatedHours;
    }

    public function roleId(): ?int
    {
        return $this->roleId;
    }
}
