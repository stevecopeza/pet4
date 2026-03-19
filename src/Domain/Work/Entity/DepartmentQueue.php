<?php

namespace Pet\Domain\Work\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Department Queue Entity.
 * 
 * Represents an item waiting in a department's unassigned queue.
 * Tracks time-to-pickup and routing logic.
 */
class DepartmentQueue
{
    public function __construct(
        private string $id,
        private string $departmentId,
        private ?string $assignedTeamId,
        private string $workItemId,
        private ?string $assignedUserId,
        private DateTimeImmutable $enteredQueueAt,
        private ?DateTimeImmutable $pickedUpAt
    ) {
    }

    public static function enter(
        string $id,
        string $departmentId,
        string $workItemId,
        ?string $assignedTeamId = null
    ): self {
        return new self(
            $id,
            $departmentId,
            $assignedTeamId,
            $workItemId,
            null,
            new DateTimeImmutable(),
            null
        );
    }

    public function assignToUser(string $userId): void
    {
        if ($this->assignedUserId !== null) {
            throw new InvalidArgumentException("Item already picked up by user: " . $this->assignedUserId);
        }

        $this->assignedUserId = $userId;
        $this->pickedUpAt = new DateTimeImmutable();
    }

    public function exitQueue(): void
    {
        if ($this->pickedUpAt !== null) {
            return;
        }
        $this->pickedUpAt = new DateTimeImmutable();
    }

    public function isUnassigned(): bool
    {
        return $this->assignedUserId === null;
    }

    public function getTimeInQueueSeconds(): int
    {
        $end = $this->pickedUpAt ?? new DateTimeImmutable();
        return $end->getTimestamp() - $this->enteredQueueAt->getTimestamp();
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getAssignedTeamId(): ?string { return $this->assignedTeamId; }
    public function getWorkItemId(): string { return $this->workItemId; }
    public function getAssignedUserId(): ?string { return $this->assignedUserId; }
    public function getEnteredQueueAt(): DateTimeImmutable { return $this->enteredQueueAt; }
    public function getPickedUpAt(): ?DateTimeImmutable { return $this->pickedUpAt; }
}
