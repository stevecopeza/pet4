<?php

declare(strict_types=1);

namespace Pet\Application\Escalation\Command;

class TriggerEscalationCommand
{
    private string $sourceEntityType;
    private int $sourceEntityId;
    private string $severity;
    private string $reason;
    private ?int $createdBy;
    private array $metadata;

    public function __construct(
        string $sourceEntityType,
        int $sourceEntityId,
        string $severity,
        string $reason,
        ?int $createdBy = null,
        array $metadata = []
    ) {
        $this->sourceEntityType = $sourceEntityType;
        $this->sourceEntityId = $sourceEntityId;
        $this->severity = $severity;
        $this->reason = $reason;
        $this->createdBy = $createdBy;
        $this->metadata = $metadata;
    }

    public function sourceEntityType(): string { return $this->sourceEntityType; }
    public function sourceEntityId(): int { return $this->sourceEntityId; }
    public function severity(): string { return $this->severity; }
    public function reason(): string { return $this->reason; }
    public function createdBy(): ?int { return $this->createdBy; }
    public function metadata(): array { return $this->metadata; }
}
