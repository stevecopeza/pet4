<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;
use Pet\Domain\Support\Entity\Ticket;
use DateTimeImmutable;

class TicketStatusChanged implements DomainEvent, SourcedEvent
{
    private Ticket $ticket;
    private string $previousStatus;
    private string $newStatus;
    private string $lifecycleOwner;
    private DateTimeImmutable $occurredAt;

    public function __construct(
        Ticket $ticket,
        string $previousStatus,
        string $newStatus,
        string $lifecycleOwner
    ) {
        $this->ticket = $ticket;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
        $this->lifecycleOwner = $lifecycleOwner;
        $this->occurredAt = new DateTimeImmutable();
    }

    public function ticket(): Ticket
    {
        return $this->ticket;
    }

    public function previousStatus(): string
    {
        return $this->previousStatus;
    }

    public function newStatus(): string
    {
        return $this->newStatus;
    }

    public function lifecycleOwner(): string
    {
        return $this->lifecycleOwner;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return (int)$this->ticket->id();
    }

    public function name(): string
    {
        return 'ticket.status_changed';
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
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'lifecycle_owner' => $this->lifecycleOwner,
        ];
    }
}
