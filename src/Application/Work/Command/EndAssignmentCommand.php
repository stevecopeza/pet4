<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class EndAssignmentCommand
{
    private int $assignmentId;
    private \DateTimeImmutable $endDate;

    public function __construct(int $assignmentId, string $endDate)
    {
        $this->assignmentId = $assignmentId;
        $this->endDate = new \DateTimeImmutable($endDate);
    }

    public function assignmentId(): int
    {
        return $this->assignmentId;
    }

    public function endDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }
}
