<?php

declare(strict_types=1);

namespace Pet\Tests\Stub;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\EventBus;

class SpyEventBus implements EventBus
{
    /** @var DomainEvent[] */
    private array $dispatched = [];
    /** @var array<string, callable[]> */
    private array $listeners = [];

    public function dispatch(DomainEvent $event): void
    {
        $this->dispatched[] = $event;
        $class = get_class($event);
        foreach ($this->listeners[$class] ?? [] as $listener) {
            $listener($event);
        }
    }

    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /** @return object[] */
    public function dispatched(): array
    {
        return $this->dispatched;
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @return T[]
     */
    public function dispatchedOfType(string $class): array
    {
        return array_values(array_filter($this->dispatched, fn(object $e) => $e instanceof $class));
    }

    public function reset(): void
    {
        $this->dispatched = [];
    }
}
