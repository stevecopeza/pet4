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
    private ?string $previousOwnerUserId;
    private ?string $previousQueueId;
    private ?string $newQueueId;
    private DateTimeImmutable $occurredAt;

    public function __construct(
        Ticket $ticket,
        ?string $assignedAgentId,
        ?string $previousOwnerUserId = null,
        ?string $previousQueueId = null,
        ?string $newQueueId = null
    ) {
        $this->ticket = $ticket;
        $this->assignedAgentId = $assignedAgentId;
        $this->previousOwnerUserId = $previousOwnerUserId;
        $this->previousQueueId = $previousQueueId;
        $this->newQueueId = $newQueueId;
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

    public function previousOwnerUserId(): ?string
    {
        return $this->previousOwnerUserId;
    }

    public function previousQueueId(): ?string
    {
        return $this->previousQueueId;
    }

    public function newQueueId(): ?string
    {
        return $this->newQueueId;
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
        return 'ticket.assigned';
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
            'previous_owner_user_id' => $this->previousOwnerUserId,
            'previous_queue_id' => $this->previousQueueId,
            'new_queue_id' => $this->newQueueId,
        ];
    }
}
