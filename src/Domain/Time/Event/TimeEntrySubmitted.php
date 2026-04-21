<?php

declare(strict_types=1);

namespace Pet\Domain\Time\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\SourcedEvent;

class TimeEntrySubmitted implements DomainEvent, SourcedEvent
{
    private int $timeEntryId;
    private int $employeeId;
    private int $minutes;
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        int $timeEntryId,
        int $employeeId,
        int $minutes
    ) {
        $this->timeEntryId = $timeEntryId;
        $this->employeeId = $employeeId;
        $this->minutes = $minutes;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function timeEntryId(): int
    {
        return $this->timeEntryId;
    }

    public function aggregateId(): int
    {
        return $this->timeEntryId;
    }

    public function name(): string
    {
        return 'time_entry.submitted';
    }

    public function aggregateType(): string
    {
        return 'time_entry';
    }

    public function aggregateVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [
            'time_entry_id' => $this->timeEntryId,
            'employee_id' => $this->employeeId,
            'minutes' => $this->minutes,
        ];
    }
}
