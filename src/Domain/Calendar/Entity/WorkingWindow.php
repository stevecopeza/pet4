<?php

declare(strict_types=1);

namespace Pet\Domain\Calendar\Entity;

class WorkingWindow
{
    private string $dayOfWeek; // 'monday', 'tuesday', etc.
    private string $startTime; // '08:00'
    private string $endTime;   // '17:00'
    private string $type;      // 'standard', 'overtime'
    private float $rateMultiplier;

    public function __construct(
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        string $type = 'standard',
        float $rateMultiplier = 1.0
    ) {
        $this->dayOfWeek = strtolower($dayOfWeek);
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->type = $type;
        $this->rateMultiplier = $rateMultiplier;

        $this->validate();
    }

    private function validate(): void
    {
        // Simple time format check
        if (!preg_match('/^\d{2}:\d{2}$/', $this->startTime) || !preg_match('/^\d{2}:\d{2}$/', $this->endTime)) {
            throw new \InvalidArgumentException("Time must be in HH:MM format");
        }
        
        // Note: end_time < start_time IS allowed for cross-midnight shifts
    }

    public function dayOfWeek(): string
    {
        return $this->dayOfWeek;
    }

    public function startTime(): string
    {
        return $this->startTime;
    }

    public function endTime(): string
    {
        return $this->endTime;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function rateMultiplier(): float
    {
        return $this->rateMultiplier;
    }
    
    public function toArray(): array
    {
        return [
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'type' => $this->type,
            'rate_multiplier' => $this->rateMultiplier,
        ];
    }
}
