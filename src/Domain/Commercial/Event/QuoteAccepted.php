<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

class QuoteAccepted implements DomainEvent, SourcedEvent
{
    private Quote $quote;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function quote(): Quote
    {
        return $this->quote;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return (int)$this->quote->id();
    }

    public function name(): string
    {
        return 'quote.accepted';
    }

    public function aggregateType(): string
    {
        return 'quote';
    }

    public function aggregateVersion(): int
    {
        return $this->quote->version();
    }

    public function toPayload(): array
    {
        return [
            'quote_id' => $this->quote->id(),
            'accepted_at' => $this->quote->acceptedAt() ? $this->quote->acceptedAt()->format('c') : null,
            'version' => $this->quote->version(),
        ];
    }
}
