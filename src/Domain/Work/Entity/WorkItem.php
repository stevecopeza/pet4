<?php

namespace Pet\Domain\Work\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * WorkItem Projection Entity.
 * 
 * Aggregates tasks, tickets, and escalations into a single priority-scored view.
 * This is a READ MODEL entity, projected from source domains (Delivery, Support, etc).
 */
class WorkItem
{
    public function __construct(
        private string $id,
        private string $sourceType,
        private string $sourceId,
        private ?string $assignedUserId,
        private string $departmentId,
        private ?int $requiredRoleId,
        private ?string $slaSnapshotId,
        private ?int $slaTimeRemainingMinutes,
        private float $priorityScore,
        private ?DateTimeImmutable $scheduledStartUtc,
        private ?DateTimeImmutable $scheduledDueUtc,
        private float $capacityAllocationPercent,
        private string $status,
        private int $escalationLevel,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private float $revenue = 0.0,
        private int $clientTier = 1,
        private float $managerPriorityOverride = 0.0
    ) {
        $this->validateSourceType($sourceType);
        $this->validateStatus($status);
    }

    public static function create(
        string $id,
        string $sourceType,
        string $sourceId,
        string $departmentId,
        float $priorityScore,
        string $status,
        DateTimeImmutable $createdAt,
        ?int $requiredRoleId = null
    ): self {
        return new self(
            $id,
            $sourceType,
            $sourceId,
            null,
            $departmentId,
            $requiredRoleId,
            null,
            null,
            $priorityScore,
            null,
            null,
            0.0,
            $status,
            0,
            $createdAt,
            $createdAt
        );
    }

    // Setters for projection updates
    public function updatePriorityScore(float $score): void
    {
        $this->priorityScore = $score;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateScheduling(?DateTimeImmutable $start, ?DateTimeImmutable $due): void
    {
        $this->scheduledStartUtc = $start;
        $this->scheduledDueUtc = $due;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function assignUser(?string $userId): void
    {
        $this->assignedUserId = $userId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateDepartment(string $departmentId): void
    {
        $this->departmentId = $departmentId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateStatus(string $status): void
    {
        $this->validateStatus($status);
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateSlaState(?string $snapshotId, ?int $minutesRemaining): void
    {
        $this->slaSnapshotId = $snapshotId;
        $this->slaTimeRemainingMinutes = $minutesRemaining;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateCapacityAllocation(float $percent): void
    {
        $this->capacityAllocationPercent = $percent;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function escalate(int $level): void
    {
        $this->escalationLevel = $level;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateCommercialInfo(float $revenue, int $clientTier): void
    {
        $this->revenue = $revenue;
        $this->clientTier = $clientTier;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setManagerPriorityOverride(float $override): void
    {
        $this->managerPriorityOverride = $override;
        $this->updatedAt = new DateTimeImmutable();
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getSourceType(): string { return $this->sourceType; }
    public function getSourceId(): string { return $this->sourceId; }
    public function getAssignedUserId(): ?string { return $this->assignedUserId; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getRequiredRoleId(): ?int { return $this->requiredRoleId; }
    public function getSlaSnapshotId(): ?string { return $this->slaSnapshotId; }
    public function getSlaTimeRemainingMinutes(): ?int { return $this->slaTimeRemainingMinutes; }
    public function getPriorityScore(): float { return $this->priorityScore; }
    public function getScheduledStartUtc(): ?DateTimeImmutable { return $this->scheduledStartUtc; }
    public function getScheduledDueUtc(): ?DateTimeImmutable { return $this->scheduledDueUtc; }
    public function getCapacityAllocationPercent(): float { return $this->capacityAllocationPercent; }
    public function getStatus(): string { return $this->status; }
    public function getEscalationLevel(): int { return $this->escalationLevel; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function getRevenue(): float { return $this->revenue; }
    public function getClientTier(): int { return $this->clientTier; }
    public function getManagerPriorityOverride(): float { return $this->managerPriorityOverride; }

    // Validation
    private function validateSourceType(string $type): void
    {
        $allowed = ['ticket', 'project_task', 'escalation', 'admin'];
        if (!in_array($type, $allowed)) {
            throw new InvalidArgumentException("Invalid source type: $type");
        }
    }

    private function validateStatus(string $status): void
    {
        $allowed = ['active', 'waiting', 'completed'];
        if (!in_array($status, $allowed)) {
            throw new InvalidArgumentException("Invalid status: $status");
        }
    }
}
