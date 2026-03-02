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

    public function __construct(
        int $ticketId,
        string $lastEventDispatched = 'none',
        ? \DateTimeImmutable $lastEvaluatedAt = null,
        int $slaVersionId = 0,
        bool $paused = false,
        int $escalationStage = 0
    ) {
        $this->ticketId = $ticketId;
        $this->lastEventDispatched = $lastEventDispatched;
        $this->lastEvaluatedAt = $lastEvaluatedAt;
        $this->slaVersionId = $slaVersionId;
        $this->paused = $paused;
        $this->escalationStage = $escalationStage;
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
}
