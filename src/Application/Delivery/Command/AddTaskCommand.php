<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

class AddTaskCommand
{
    private int $projectId;
    private string $name;
    private float $estimatedHours;

    public function __construct(int $projectId, string $name, float $estimatedHours)
    {
        $this->projectId = $projectId;
        $this->name = $name;
        $this->estimatedHours = $estimatedHours;
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
}
