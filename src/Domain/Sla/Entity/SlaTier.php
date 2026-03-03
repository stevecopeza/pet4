<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Entity;

class SlaTier
{
    private ?int $id;
    private int $priority;
    private string $label;
    private int $calendarId;
    private int $responseTargetMinutes;
    private int $resolutionTargetMinutes;
    private array $escalationRules;

    public function __construct(
        int $priority,
        string $label,
        int $calendarId,
        int $responseTargetMinutes,
        int $resolutionTargetMinutes,
        array $escalationRules = [],
        ?int $id = null
    ) {
        $this->id = $id;
        $this->priority = $priority;
        $this->label = $label;
        $this->calendarId = $calendarId;
        $this->responseTargetMinutes = $responseTargetMinutes;
        $this->resolutionTargetMinutes = $resolutionTargetMinutes;
        $this->escalationRules = $escalationRules;

        $this->validate();
    }

    private function validate(): void
    {
        if ($this->priority < 1) {
            throw new \DomainException("Tier priority must be a positive integer.");
        }
        if ($this->responseTargetMinutes <= 0 || $this->resolutionTargetMinutes <= 0) {
            throw new \DomainException("Tier targets must be positive integers.");
        }
        if ($this->responseTargetMinutes > $this->resolutionTargetMinutes) {
            throw new \DomainException("Tier response target cannot exceed resolution target.");
        }
    }

    public function id(): ?int { return $this->id; }
    public function priority(): int { return $this->priority; }
    public function label(): string { return $this->label; }
    public function calendarId(): int { return $this->calendarId; }
    public function responseTargetMinutes(): int { return $this->responseTargetMinutes; }
    public function resolutionTargetMinutes(): int { return $this->resolutionTargetMinutes; }
    public function escalationRules(): array { return $this->escalationRules; }
}
