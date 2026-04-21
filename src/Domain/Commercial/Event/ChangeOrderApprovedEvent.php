<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;
use Pet\Domain\Commercial\Entity\CostAdjustment;

class ChangeOrderApprovedEvent implements DomainEvent, SourcedEvent
{
    private CostAdjustment $costAdjustment;
    private \DateTimeImmutable $occurredAt;

    public function __construct(CostAdjustment $costAdjustment)
    {
        $this->costAdjustment = $costAdjustment;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function costAdjustment(): CostAdjustment
    {
        return $this->costAdjustment;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return (int)$this->costAdjustment->quoteId();
    }

    public function name(): string
    {
        return 'quote.change_order_approved';
    }

    public function aggregateType(): string
    {
        return 'quote';
    }

    public function aggregateVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [
            'quote_id' => $this->costAdjustment->quoteId(),
            'adjustment_id' => $this->costAdjustment->id(),
            'description' => $this->costAdjustment->description(),
            'amount' => $this->costAdjustment->amount(),
            'reason' => $this->costAdjustment->reason(),
            'approved_by' => $this->costAdjustment->approvedBy(),
        ];
    }
}
