<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Event\Repository\EventStreamRepository;
use Pet\Domain\Event\SourcedEvent;

class PersistingEventBus implements EventBus
{
    private EventBus $inner;
    private EventStreamRepository $eventStream;

    public function __construct(EventBus $inner, EventStreamRepository $eventStream)
    {
        $this->inner = $inner;
        $this->eventStream = $eventStream;
    }

    public function dispatch(DomainEvent $event): void
    {
        if ($event instanceof SourcedEvent) {
            $this->persist($event);
        }
        $this->inner->dispatch($event);
    }

    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->inner->subscribe($eventClass, $listener);
    }

    private function persist(SourcedEvent $event): void
    {
        $payload = json_encode($event->toPayload());
        $aggregateType = $event->aggregateType();
        $aggregateId = $event->aggregateId();
        
        // Calculate next version to ensure append succeeds
        // We ignore the version on the event itself for now to ensure stream consistency
        // independent of entity versioning state.
        $version = $this->eventStream->nextVersion($aggregateType, $aggregateId);

        $this->eventStream->append(
            $aggregateType,
            $aggregateId,
            $version,
            get_class($event),
            $payload
        );
    }
}
