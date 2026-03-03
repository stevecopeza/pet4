<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Entity;

class TierTransition
{
    private ?int $id;
    private int $ticketId;
    private ?int $fromTierPriority;
    private int $toTierPriority;
    private float $actualPercentAtTransition;
    private float $carriedPercent;
    private ?string $overrideReason;
    private \DateTimeImmutable $transitionedAt;

    public function __construct(
        int $ticketId,
        ?int $fromTierPriority,
        int $toTierPriority,
        float $actualPercentAtTransition,
        float $carriedPercent,
        ?string $overrideReason = null,
        ?\DateTimeImmutable $transitionedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->ticketId = $ticketId;
        $this->fromTierPriority = $fromTierPriority;
        $this->toTierPriority = $toTierPriority;
        $this->actualPercentAtTransition = $actualPercentAtTransition;
        $this->carriedPercent = $carriedPercent;
        $this->overrideReason = $overrideReason;
        $this->transitionedAt = $transitionedAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int { return $this->id; }
    public function ticketId(): int { return $this->ticketId; }
    public function fromTierPriority(): ?int { return $this->fromTierPriority; }
    public function toTierPriority(): int { return $this->toTierPriority; }
    public function actualPercentAtTransition(): float { return $this->actualPercentAtTransition; }
    public function carriedPercent(): float { return $this->carriedPercent; }
    public function overrideReason(): ?string { return $this->overrideReason; }
    public function transitionedAt(): \DateTimeImmutable { return $this->transitionedAt; }
    public function isManualOverride(): bool { return $this->overrideReason !== null; }
}
