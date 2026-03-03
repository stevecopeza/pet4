<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

class SLATierTransitionedEvent implements DomainEvent, SourcedEvent
{
    private int $ticketId;
    private ?int $fromTierPriority;
    private int $toTierPriority;
    private float $carriedPercent;
    private ?string $overrideReason;
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        int $ticketId,
        ?int $fromTierPriority,
        int $toTierPriority,
        float $carriedPercent,
        ?string $overrideReason = null
    ) {
        $this->ticketId = $ticketId;
        $this->fromTierPriority = $fromTierPriority;
        $this->toTierPriority = $toTierPriority;
        $this->carriedPercent = $carriedPercent;
        $this->overrideReason = $overrideReason;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function ticketId(): int { return $this->ticketId; }
    public function fromTierPriority(): ?int { return $this->fromTierPriority; }
    public function toTierPriority(): int { return $this->toTierPriority; }
    public function carriedPercent(): float { return $this->carriedPercent; }
    public function overrideReason(): ?string { return $this->overrideReason; }
    public function isManualOverride(): bool { return $this->overrideReason !== null; }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return $this->ticketId;
    }

    public function aggregateType(): string
    {
        return 'ticket';
    }

    public function aggregateVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'from_tier_priority' => $this->fromTierPriority,
            'to_tier_priority' => $this->toTierPriority,
            'carried_percent' => $this->carriedPercent,
            'override_reason' => $this->overrideReason,
        ];
    }
}
