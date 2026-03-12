<?php

declare(strict_types=1);

namespace Pet\Domain\Escalation\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

class EscalationAcknowledgedEvent implements DomainEvent, SourcedEvent
{
    private string $escalationId;
    private string $sourceEntityType;
    private int $sourceEntityId;
    private string $severity;
    private int $acknowledgedBy;
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        string $escalationId,
        string $sourceEntityType,
        int $sourceEntityId,
        string $severity,
        int $acknowledgedBy
    ) {
        $this->escalationId = $escalationId;
        $this->sourceEntityType = $sourceEntityType;
        $this->sourceEntityId = $sourceEntityId;
        $this->severity = $severity;
        $this->acknowledgedBy = $acknowledgedBy;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function escalationId(): string { return $this->escalationId; }
    public function sourceEntityType(): string { return $this->sourceEntityType; }
    public function sourceEntityId(): int { return $this->sourceEntityId; }
    public function severity(): string { return $this->severity; }
    public function acknowledgedBy(): int { return $this->acknowledgedBy; }

    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }

    public function aggregateId(): int { return $this->sourceEntityId; }
    public function aggregateType(): string { return 'escalation'; }
    public function aggregateVersion(): int { return 1; }

    public function toPayload(): array
    {
        return [
            'escalation_id' => $this->escalationId,
            'source_entity_type' => $this->sourceEntityType,
            'source_entity_id' => $this->sourceEntityId,
            'severity' => $this->severity,
            'acknowledged_by' => $this->acknowledgedBy,
        ];
    }
}
