<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Entity;

class SlaClockState
{
    private int $id;
    private int $ticketId;
    private string $lastEventDispatched;
    private ?\DateTimeImmutable $lastEvaluatedAt;
    private int $slaVersionId;
    private bool $paused;
    private int $escalationStage;
    private ?int $activeTierPriority;
    private int $tierElapsedBusinessMinutes;
    private ?float $carriedForwardPercent;
    private int $totalTransitions;

    public function __construct(
        int $ticketId,
        string $lastEventDispatched = 'none',
        ?\DateTimeImmutable $lastEvaluatedAt = null,
        int $slaVersionId = 0,
        bool $paused = false,
        int $escalationStage = 0,
        ?int $activeTierPriority = null,
        int $tierElapsedBusinessMinutes = 0,
        ?float $carriedForwardPercent = null,
        int $totalTransitions = 0
    ) {
        $this->ticketId = $ticketId;
        $this->lastEventDispatched = $lastEventDispatched;
        $this->lastEvaluatedAt = $lastEvaluatedAt;
        $this->slaVersionId = $slaVersionId;
        $this->paused = $paused;
        $this->escalationStage = $escalationStage;
        $this->activeTierPriority = $activeTierPriority;
        $this->tierElapsedBusinessMinutes = $tierElapsedBusinessMinutes;
        $this->carriedForwardPercent = $carriedForwardPercent;
        $this->totalTransitions = $totalTransitions;
    }

    public function getLastEventDispatched(): string
    {
        return $this->lastEventDispatched;
    }

    public function setLastEventDispatched(string $state): void
    {
        $this->lastEventDispatched = $state;
    }

    public function setLastEvaluatedAt(\DateTimeImmutable $date): void
    {
        $this->lastEvaluatedAt = $date;
    }

    public function getTicketId(): int
    {
        return $this->ticketId;
    }

    public function getLastEvaluatedAt(): ?\DateTimeImmutable
    {
        return $this->lastEvaluatedAt;
    }

    public function getSlaVersionId(): int
    {
        return $this->slaVersionId;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function getEscalationStage(): int
    {
        return $this->escalationStage;
    }

    public function setEscalationStage(int $stage): void
    {
        $this->escalationStage = $stage;
    }

    public function setPaused(bool $paused): void
    {
        $this->paused = $paused;
    }

    // Tier-tracking accessors

    public function getActiveTierPriority(): ?int
    {
        return $this->activeTierPriority;
    }

    public function setActiveTierPriority(?int $priority): void
    {
        $this->activeTierPriority = $priority;
    }

    public function getTierElapsedBusinessMinutes(): int
    {
        return $this->tierElapsedBusinessMinutes;
    }

    public function setTierElapsedBusinessMinutes(int $minutes): void
    {
        $this->tierElapsedBusinessMinutes = $minutes;
    }

    public function getCarriedForwardPercent(): ?float
    {
        return $this->carriedForwardPercent;
    }

    public function setCarriedForwardPercent(?float $percent): void
    {
        $this->carriedForwardPercent = $percent;
    }

    public function getTotalTransitions(): int
    {
        return $this->totalTransitions;
    }

    public function incrementTransitions(): void
    {
        $this->totalTransitions++;
    }
}
