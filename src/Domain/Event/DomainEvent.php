<?php

declare(strict_types=1);

namespace Pet\Domain\Event;

/**
 * Marker interface for all domain events.
 */
interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
