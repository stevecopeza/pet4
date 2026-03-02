<?php

declare(strict_types=1);

namespace Pet\Domain\Calendar\Entity;

class Calendar
{
    private ?int $id;
    private string $uuid;
    private string $name;
    private string $timezone;
    private array $workingWindows; // Array of WorkingWindow
    private array $holidays;       // Array of Holiday
    private bool $isDefault;

    public function __construct(
        string $name,
        string $timezone = 'UTC',
        array $workingWindows = [],
        array $holidays = [],
        bool $isDefault = false,
        ?int $id = null,
        ?string $uuid = null
    ) {
        $this->id = $id;
        $this->uuid = $uuid ?? $this->generateUuid();
        $this->name = $name;
        $this->timezone = $timezone;
        $this->workingWindows = $workingWindows;
        $this->holidays = $holidays;
        $this->isDefault = $isDefault;

        $this->validateTimezone();
        $this->validateWindows();
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function validateTimezone(): void
    {
        if (!in_array($this->timezone, \DateTimeZone::listIdentifiers())) {
            throw new \InvalidArgumentException("Invalid timezone: {$this->timezone}");
        }
    }

    private function validateWindows(): void
    {
        // Check for overlapping windows within the same day
        // This is a simplified check; a robust one handles minute-level overlap
        $windowsByDay = [];
        foreach ($this->workingWindows as $window) {
            $day = $window->dayOfWeek();
            if (!isset($windowsByDay[$day])) {
                $windowsByDay[$day] = [];
            }
            $windowsByDay[$day][] = $window;
        }

        foreach ($windowsByDay as $day => $windows) {
            usort($windows, fn($a, $b) => strcmp($a->startTime(), $b->startTime()));
            
            for ($i = 0; $i < count($windows) - 1; $i++) {
                $current = $windows[$i];
                $next = $windows[$i + 1];
                
                // If current ends after next starts, we have an overlap
                // Note: Ignoring cross-midnight logic for this basic validation for now
                if ($current->endTime() > $next->startTime()) {
                    throw new \DomainException("Overlapping working windows detected on {$day}");
                }
            }
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function workingWindows(): array
    {
        return $this->workingWindows;
    }

    public function holidays(): array
    {
        return $this->holidays;
    }
    
    public function isDefault(): bool
    {
        return $this->isDefault;
    }
    
    public function createSnapshot(): array
    {
        return [
            'calendar_id' => $this->uuid,
            'timezone' => $this->timezone,
            'working_windows' => array_map(fn($w) => $w->toArray(), $this->workingWindows),
            'holidays' => array_map(fn($h) => $h->toArray(), $this->holidays),
        ];
    }
}
