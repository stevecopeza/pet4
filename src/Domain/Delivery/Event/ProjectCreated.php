<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;
use Pet\Domain\Delivery\Entity\Project;

class ProjectCreated implements DomainEvent, SourcedEvent
{
    private Project $project;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return (int)$this->project->id();
    }

    public function aggregateType(): string
    {
        return 'project';
    }

    public function aggregateVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [
            'project_id' => $this->project->id(),
            'name' => $this->project->name(),
            'customer_id' => $this->project->customerId(),
        ];
    }
}
