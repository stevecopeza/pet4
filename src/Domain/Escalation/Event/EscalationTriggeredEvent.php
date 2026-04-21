<?php

declare(strict_types=1);

namespace Pet\Domain\Escalation\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

class EscalationTriggeredEvent implements DomainEvent, SourcedEvent
{
    private string $escalationId;
    private string $sourceEntityType;
    private int $sourceEntityId;
    private string $severity;
    private string $reason;
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        string $escalationId,
        string $sourceEntityType,
        int $sourceEntityId,
        string $severity,
        string $reason
    ) {
        $this->escalationId = $escalationId;
        $this->sourceEntityType = $sourceEntityType;
        $this->sourceEntityId = $sourceEntityId;
        $this->severity = $severity;
        $this->reason = $reason;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function escalationId(): string { return $this->escalationId; }
    public function sourceEntityType(): string { return $this->sourceEntityType; }
    public function sourceEntityId(): int { return $this->sourceEntityId; }
    public function severity(): string { return $this->severity; }
    public function reason(): string { return $this->reason; }

    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }

    public function aggregateId(): int { return $this->sourceEntityId; }
    public function name(): string { return 'escalation.triggered'; }
    public function aggregateType(): string { return 'escalation'; }
    public function aggregateVersion(): int { return 1; }

    public function toPayload(): array
    {
        return [
            'escalation_id' => $this->escalationId,
            'source_entity_type' => $this->sourceEntityType,
            'source_entity_id' => $this->sourceEntityId,
            'severity' => $this->severity,
            'reason' => $this->reason,
        ];
    }
}
