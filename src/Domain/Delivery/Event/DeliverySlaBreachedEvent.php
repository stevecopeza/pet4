<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

/**
 * Dispatched once when a delivery ticket first breaches its SLA deadline.
 *
 * "Breached" is defined as: now > resolution_due_at.
 * Subsequent cron runs while the ticket remains breached do NOT re-dispatch
 * this event — the transition is recorded in sla_status and compared each run.
 *
 * Consumers: advisory signal generator, notification dispatch, escalation rules.
 */
class DeliverySlaBreachedEvent implements DomainEvent, SourcedEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        private readonly int    $ticketId,
        private readonly ?int   $projectId,
        private readonly string $resolutionDueAt
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function ticketId(): int
    {
        return $this->ticketId;
    }

    public function projectId(): ?int
    {
        return $this->projectId;
    }

    public function resolutionDueAt(): string
    {
        return $this->resolutionDueAt;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    // ── SourcedEvent ──────────────────────────────────────────────────────────

    public function aggregateId(): int
    {
        return $this->ticketId;
    }

    public function name(): string
    {
        return 'ticket.delivery_sla_breached';
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
            'ticket_id'          => $this->ticketId,
            'project_id'         => $this->projectId,
            'resolution_due_at'  => $this->resolutionDueAt,
        ];
    }
}
