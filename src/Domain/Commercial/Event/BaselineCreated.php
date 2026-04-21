<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;
use Pet\Domain\Commercial\Entity\Baseline;

class BaselineCreated implements DomainEvent, SourcedEvent
{
    private Baseline $baseline;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Baseline $baseline)
    {
        $this->baseline = $baseline;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function baseline(): Baseline
    {
        return $this->baseline;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return (int)$this->baseline->contractId();
    }

    public function name(): string
    {
        return 'baseline.created';
    }

    public function aggregateType(): string
    {
        return 'contract';
    }

    public function aggregateVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [
            'baseline_id' => $this->baseline->id(),
            'contract_id' => $this->baseline->contractId(),
            'total_value' => $this->baseline->totalValue(),
            'total_internal_cost' => $this->baseline->totalInternalCost(),
        ];
    }
}

