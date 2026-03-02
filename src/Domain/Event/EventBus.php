<?php

declare(strict_types=1);

namespace Pet\Domain\Event;

interface EventBus
{
    public function dispatch(DomainEvent $event): void;
    public function subscribe(string $eventClass, callable $listener): void;
}
