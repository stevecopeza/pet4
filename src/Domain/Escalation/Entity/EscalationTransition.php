<?php

declare(strict_types=1);

namespace Pet\Domain\Escalation\Entity;

class EscalationTransition
{
    private ?int $id;
    private int $escalationId;
    private ?string $fromStatus;
    private string $toStatus;
    private ?int $transitionedBy;
    private \DateTimeImmutable $transitionedAt;
    private ?string $reason;

    public function __construct(
        int $escalationId,
        ?string $fromStatus,
        string $toStatus,
        ?int $transitionedBy = null,
        ?string $reason = null,
        ?\DateTimeImmutable $transitionedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->escalationId = $escalationId;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->transitionedBy = $transitionedBy;
        $this->reason = $reason;
        $this->transitionedAt = $transitionedAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int { return $this->id; }
    public function escalationId(): int { return $this->escalationId; }
    public function fromStatus(): ?string { return $this->fromStatus; }
    public function toStatus(): string { return $this->toStatus; }
    public function transitionedBy(): ?int { return $this->transitionedBy; }
    public function transitionedAt(): \DateTimeImmutable { return $this->transitionedAt; }
    public function reason(): ?string { return $this->reason; }
}
