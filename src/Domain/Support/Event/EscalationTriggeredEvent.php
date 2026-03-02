<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

class EscalationTriggeredEvent implements DomainEvent, SourcedEvent
{
    private int $ticketId;
    private int $stage;
    private \DateTimeImmutable $occurredAt;

    public function __construct(int $ticketId, int $stage)
    {
        $this->ticketId = $ticketId;
        $this->stage = $stage;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function ticketId(): int
    {
        return $this->ticketId;
    }

    public function stage(): int
    {
        return $this->stage;
    }

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
            'stage' => $this->stage,
        ];
    }
}
