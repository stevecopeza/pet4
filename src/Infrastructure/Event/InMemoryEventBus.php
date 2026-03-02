<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\EventBus;

class InMemoryEventBus implements EventBus
{
    private array $listeners = [];

    public function dispatch(DomainEvent $event): void
    {
        $eventClass = get_class($event);
        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            $listener($event);
        }
    }

    public function subscribe(string $eventClass, callable $listener): void
    {
        if (!isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }
        $this->listeners[$eventClass][] = $listener;
    }
}
