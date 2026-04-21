<?php

declare(strict_types=1);

namespace Pet\Domain\Event;

interface SourcedEvent extends DomainEvent
{
    /**
     * Stable, dotted event name used when persisting to the event stream.
     * Format: {aggregate_type}.{past_tense_verb}  (e.g. "quote.accepted")
     * Once stored in the database this value must never change.
     */
    public function name(): string;

    public function aggregateId(): int;
    public function aggregateType(): string;
    public function aggregateVersion(): int;
    public function toPayload(): array;
}
