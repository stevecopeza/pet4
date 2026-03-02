<?php

declare(strict_types=1);

namespace Pet\Domain\Event;

interface SourcedEvent extends DomainEvent
{
    public function aggregateId(): int;
    public function aggregateType(): string;
    public function aggregateVersion(): int;
    public function toPayload(): array;
}
