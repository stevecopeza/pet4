<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

class MarkMilestoneCompleteCommand
{
    private int $projectId;
    private string $milestoneTitle;

    public function __construct(int $projectId, string $milestoneTitle)
    {
        $this->projectId = $projectId;
        $this->milestoneTitle = $milestoneTitle;
    }

    public function projectId(): int
    {
        return $this->projectId;
    }

    public function milestoneTitle(): string
    {
        return $this->milestoneTitle;
    }
}
