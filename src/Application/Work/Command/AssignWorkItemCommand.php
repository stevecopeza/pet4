<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class AssignWorkItemCommand
{
    public function __construct(
        private string $workItemId,
        private string $assignedUserId
    ) {}

    public function workItemId(): string
    {
        return $this->workItemId;
    }

    public function assignedUserId(): string
    {
        return $this->assignedUserId;
    }
}
