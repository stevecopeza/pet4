<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Entity;

class EscalationRule
{
    private ?int $id;
    private int $thresholdPercent;
    private string $action;
    private string $criteriaJson;
    private bool $isEnabled;

    public function __construct(
        int $thresholdPercent,
        string $action,
        ?int $id = null,
        string $criteriaJson = '{}',
        bool $isEnabled = true
    ) {
        $this->id = $id;
        $this->thresholdPercent = $thresholdPercent;
        $this->action = $action;
        $this->criteriaJson = $criteriaJson;
        $this->isEnabled = $isEnabled;
        
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->thresholdPercent < 1 || $this->thresholdPercent > 100) {
            throw new \DomainException("Threshold percent must be between 1 and 100.");
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function thresholdPercent(): int
    {
        return $this->thresholdPercent;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function criteriaJson(): string
    {
        return $this->criteriaJson;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }
}
