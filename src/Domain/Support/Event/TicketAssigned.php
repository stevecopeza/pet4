<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;
use Pet\Domain\Support\Entity\Ticket;
use DateTimeImmutable;

class TicketAssigned implements DomainEvent, SourcedEvent
{
    private Ticket $ticket;
    private ?string $assignedAgentId;
    private DateTimeImmutable $occurredAt;

    public function __construct(Ticket $ticket, ?string $assignedAgentId)
    {
        $this->ticket = $ticket;
        $this->assignedAgentId = $assignedAgentId;
        $this->occurredAt = new DateTimeImmutable();
    }

    public function ticket(): Ticket
    {
        return $this->ticket;
    }

    public function assignedAgentId(): ?string
    {
        return $this->assignedAgentId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return (int)$this->ticket->id();
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
            'ticket_id' => $this->ticket->id(),
            'assigned_agent_id' => $this->assignedAgentId,
        ];
    }
}
