<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

class UpdateDraftTimeEntryCommand
{
    private int $timeEntryId;
    private string $description;
    private \DateTimeImmutable $start;
    private \DateTimeImmutable $end;
    private bool $isBillable;

    public function __construct(
        int $timeEntryId,
        string $description,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        bool $isBillable
    ) {
        $this->timeEntryId = $timeEntryId;
        $this->description = $description;
        $this->start = $start;
        $this->end = $end;
        $this->isBillable = $isBillable;
    }

    public function timeEntryId(): int { return $this->timeEntryId; }
    public function description(): string { return $this->description; }
    public function start(): \DateTimeImmutable { return $this->start; }
    public function end(): \DateTimeImmutable { return $this->end; }
    public function isBillable(): bool { return $this->isBillable; }
}
