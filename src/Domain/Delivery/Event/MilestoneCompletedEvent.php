<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

class MilestoneCompletedEvent implements DomainEvent, SourcedEvent
{
    private int $projectId;
    private string $milestoneTitle;
    private \DateTimeImmutable $occurredAt;

    public function __construct(int $projectId, string $milestoneTitle)
    {
        $this->projectId = $projectId;
        $this->milestoneTitle = $milestoneTitle;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function projectId(): int
    {
        return $this->projectId;
    }

    public function milestoneTitle(): string
    {
        return $this->milestoneTitle;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return $this->projectId;
    }

    public function name(): string
    {
        return 'project.milestone_completed';
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
            'project_id' => $this->projectId,
            'milestone_title' => $this->milestoneTitle,
        ];
    }
}
