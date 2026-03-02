<?php

declare(strict_types=1);

namespace Pet\Domain\Calendar\Entity;

class Holiday
{
    private string $name;
    private \DateTimeImmutable $date;
    private bool $isRecurring;

    public function __construct(string $name, \DateTimeImmutable $date, bool $isRecurring = false)
    {
        $this->name = $name;
        $this->date = $date->setTime(0, 0, 0); // Normalized to midnight
        $this->isRecurring = $isRecurring;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }
    
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'date' => $this->date->format('Y-m-d'),
            'is_recurring' => $this->isRecurring,
        ];
    }
}
