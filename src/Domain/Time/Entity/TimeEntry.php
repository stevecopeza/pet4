<?php

declare(strict_types=1);

namespace Pet\Domain\Time\Entity;

use Pet\Domain\Time\Event\TimeEntrySubmitted;

class TimeEntry
{
    private ?int $id;
    private int $employeeId;
    private int $ticketId;
    private \DateTimeImmutable $start;
    private \DateTimeImmutable $end;
    private int $durationMinutes;
    private bool $isBillable;
    private string $description;
    private string $status; // draft, submitted, locked
    private array $malleableData;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $archivedAt;

    private const STATUS_DRAFT = 'draft';
    private const STATUS_SUBMITTED = 'submitted';
    private const STATUS_LOCKED = 'locked';

    private array $domainEvents = [];

    public function __construct(
        int $employeeId,
        int $ticketId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        bool $isBillable,
        string $description,
        string $status = self::STATUS_DRAFT,
        ?int $id = null,
        array $malleableData = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $archivedAt = null
    ) {
        if ($end <= $start) {
            throw new \DomainException('End time must be after start time.');
        }

        $this->id = $id;
        $this->employeeId = $employeeId;
        $this->ticketId = $ticketId;
        $this->start = $start;
        $this->end = $end;
        $this->isBillable = $isBillable;
        $this->description = $description;
        $this->status = $status;
        $this->malleableData = $malleableData;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->archivedAt = $archivedAt;
        
        $this->durationMinutes = (int) ceil(($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function archive(): void
    {
        $this->archivedAt = new \DateTimeImmutable();
    }


    public function submit(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('Only draft time entries can be submitted.');
        }

        $this->status = self::STATUS_SUBMITTED;
        
        // In a real CQRS/Event Sourcing setup, we'd record this event
        if ($this->id) {
             $this->recordEvent(new TimeEntrySubmitted($this->id, $this->employeeId, $this->durationMinutes));
        }
    }

    public function lock(): void
    {
        $this->status = self::STATUS_LOCKED;
    }

    private function recordEvent($event): void
    {
        $this->domainEvents[] = $event;
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // Immutable getters
    public function id(): ?int { return $this->id; }
    public function employeeId(): int { return $this->employeeId; }
    public function ticketId(): int { return $this->ticketId; }
    public function start(): \DateTimeImmutable { return $this->start; }
    public function end(): \DateTimeImmutable { return $this->end; }
    public function durationMinutes(): int { return $this->durationMinutes; }
    public function isBillable(): bool { return $this->isBillable; }
    public function description(): string { return $this->description; }
    public function status(): string { return $this->status; }
}
