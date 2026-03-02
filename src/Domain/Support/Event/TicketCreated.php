<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;
use Pet\Domain\Support\Entity\Ticket;

class TicketCreated implements DomainEvent, SourcedEvent
{
    private $ticket;
    private $occurredAt;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function ticket(): Ticket
    {
        return $this->ticket;
    }

    public function occurredAt(): \DateTimeImmutable
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
            'customer_id' => $this->ticket->customerId(),
        ];
    }
}
