<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Entity\Task;

class ProjectTaskCreated implements DomainEvent, SourcedEvent
{
    private Project $project;
    private Task $task;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Project $project, Task $task)
    {
        $this->project = $project;
        $this->task = $task;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function task(): Task
    {
        return $this->task;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return (int)$this->project->id();
    }

    public function name(): string
    {
        return 'project.task_created';
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
            'task' => [
                'id' => $this->task->id(),
                'name' => $this->task->name(),
                'estimated_hours' => $this->task->estimatedHours(),
                'role_id' => $this->task->roleId(),
                'completed' => $this->task->isCompleted(),
            ],
        ];
    }
}
