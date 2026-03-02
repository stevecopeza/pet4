<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;
use Pet\Domain\Commercial\Entity\Contract;

class ContractCreated implements DomainEvent, SourcedEvent
{
    private Contract $contract;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Contract $contract)
    {
        $this->contract = $contract;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function contract(): Contract
    {
        return $this->contract;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return (int)$this->contract->id();
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
            'contract_id' => $this->contract->id(),
            'quote_id' => $this->contract->quoteId(),
            'customer_id' => $this->contract->customerId(),
            'total_value' => $this->contract->totalValue(),
            'currency' => $this->contract->currency(),
        ];
    }
}
