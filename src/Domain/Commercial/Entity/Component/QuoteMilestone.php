<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

class QuoteMilestone
{
    private ?int $id;
    private string $title;
    private ?string $description;
    /** @var QuoteTask[] */
    private array $tasks;

    public function __construct(
        string $title,
        array $tasks = [],
        ?string $description = null,
        ?int $id = null
    ) {
        $this->title = $title;
        $this->tasks = $tasks;
        $this->description = $description;
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /** @return QuoteTask[] */
    public function tasks(): array
    {
        return $this->tasks;
    }

    public function addTask(QuoteTask $task): void
    {
        $this->tasks[] = $task;
    }

    public function sellValue(): float
    {
        $total = 0.0;
        foreach ($this->tasks as $task) {
            $total += $task->sellValue();
        }
        return $total;
    }

    public function internalCost(): float
    {
        $total = 0.0;
        foreach ($this->tasks as $task) {
            $total += $task->internalCost();
        }
        return $total;
    }
}
