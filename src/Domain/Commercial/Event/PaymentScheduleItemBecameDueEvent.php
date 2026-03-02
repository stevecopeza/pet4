<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Event\DomainEvent;

final class PaymentScheduleItemBecameDueEvent implements DomainEvent
{
    private int $quoteId;
    private int $milestoneId;
    private string $title;
    private float $amount;
    private ?\DateTimeImmutable $dueDate;
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        int $quoteId,
        int $milestoneId,
        string $title,
        float $amount,
        ?\DateTimeImmutable $dueDate
    ) {
        $this->quoteId = $quoteId;
        $this->milestoneId = $milestoneId;
        $this->title = $title;
        $this->amount = $amount;
        $this->dueDate = $dueDate;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function milestoneId(): int
    {
        return $this->milestoneId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

