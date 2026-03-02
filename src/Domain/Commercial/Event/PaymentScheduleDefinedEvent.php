<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

final class PaymentScheduleDefinedEvent implements DomainEvent, SourcedEvent
{
    private int $quoteId;
    private float $totalAmount;
    private array $items;
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array $items Array of ['id' => int|null, 'title' => string, 'amount' => float, 'dueDate' => ?\DateTimeImmutable]
     */
    public function __construct(int $quoteId, float $totalAmount, array $items)
    {
        $this->quoteId = $quoteId;
        $this->totalAmount = $totalAmount;
        $this->items = $items;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function totalAmount(): float
    {
        return $this->totalAmount;
    }

    public function items(): array
    {
        return $this->items;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return $this->quoteId;
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
        $serializedItems = array_map(function ($item) {
            $newItem = $item;
            if (isset($newItem['dueDate']) && $newItem['dueDate'] instanceof \DateTimeInterface) {
                $newItem['dueDate'] = $newItem['dueDate']->format('Y-m-d H:i:s');
            }
            return $newItem;
        }, $this->items);

        return [
            'quote_id' => $this->quoteId,
            'total_amount' => $this->totalAmount,
            'items' => $serializedItems,
        ];
    }
}

