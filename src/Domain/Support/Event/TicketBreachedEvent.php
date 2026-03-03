<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

class TicketBreachedEvent implements DomainEvent, SourcedEvent
{
    private int $ticketId;
    private ?int $tierPriority;
    private \DateTimeImmutable $occurredAt;

    public function __construct(int $ticketId, ?int $tierPriority = null)
    {
        $this->ticketId = $ticketId;
        $this->tierPriority = $tierPriority;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getTicketId(): int
    {
        return $this->ticketId;
    }

    public function tierPriority(): ?int
    {
        return $this->tierPriority;
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
            'tier_priority' => $this->tierPriority,
        ];
    }
}
