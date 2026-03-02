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
    private ?int $correctsEntryId;

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
        ?\DateTimeImmutable $archivedAt = null,
        ?int $correctsEntryId = null
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
        $this->correctsEntryId = $correctsEntryId;
        
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

    /**
     * Update a draft time entry. Only callable when status is 'draft'.
     * ticketId and employeeId are immutable — only description, time range, and billable flag can change.
     */
    public function updateDraft(
        string $description,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        bool $isBillable
    ): void {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('Only draft time entries can be edited.');
        }

        if ($end <= $start) {
            throw new \DomainException('End time must be after start time.');
        }

        $this->description = $description;
        $this->start = $start;
        $this->end = $end;
        $this->isBillable = $isBillable;
        $this->durationMinutes = (int) ceil(($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    /**
     * Set the ID after initial persistence. Only callable once (when id is null).
     */
    public function setId(int $id): void
    {
        if ($this->id !== null) {
            throw new \DomainException('Cannot change an already-assigned ID.');
        }
        $this->id = $id;
    }

    /**
     * Create a correction entry linked to this (original) entry.
     * Used for reversals (negative duration via swapped start/end concept)
     * or reclassification (e.g. changing billable flag on a locked entry).
     *
     * The correction is created as a draft so it can be reviewed before submission.
     */
    public static function createCorrection(
        self $original,
        string $description,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        bool $isBillable
    ): self {
        if (!$original->id()) {
            throw new \DomainException('Cannot correct an unsaved time entry.');
        }

        return new self(
            $original->employeeId(),
            $original->ticketId(),
            $start,
            $end,
            $isBillable,
            $description,
            self::STATUS_DRAFT,
            null,
            ['correction_type' => 'manual', 'original_description' => $original->description()],
            null,
            null,
            $original->id()
        );
    }

    /**
     * Create a full reversal of this entry (negative mirror).
     * Swaps start/end conceptually by using same times but marking duration as negative.
     */
    public static function createReversal(self $original, string $reason = ''): self
    {
        if (!$original->id()) {
            throw new \DomainException('Cannot reverse an unsaved time entry.');
        }

        $description = 'REVERSAL: ' . ($reason ?: $original->description());

        // Create with same times — the negative semantic is in the description/malleable data
        $entry = new self(
            $original->employeeId(),
            $original->ticketId(),
            $original->start(),
            $original->end(),
            $original->isBillable(),
            $description,
            self::STATUS_DRAFT,
            null,
            ['correction_type' => 'reversal', 'reversed_minutes' => $original->durationMinutes()],
            null,
            null,
            $original->id()
        );

        return $entry;
    }

    public function isCorrection(): bool
    {
        return $this->correctsEntryId !== null;
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
    public function correctsEntryId(): ?int { return $this->correctsEntryId; }
}
