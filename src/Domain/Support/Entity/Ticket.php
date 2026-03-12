<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Entity;

use Pet\Domain\Support\Event\TicketAssigned;
use Pet\Domain\Support\Event\TicketStatusChanged;
use Pet\Domain\Support\ValueObject\TicketStatus;

class Ticket
{
    private array $domainEvents = [];
    private ?int $id;
    private int $customerId;
    private ?int $siteId;
    private string $subject;
    private string $description;
    private string $status;
    private string $priority;
    private ?int $slaId;
    private ?string $queueId = null;
    private ?string $ownerUserId = null;
    private ?string $category = null;
    private ?string $subcategory = null;
    private ?string $intakeSource = null;
    private ?int $contactId = null;
    private ?int $malleableSchemaVersion;
    private array $malleableData;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $openedAt;
    private ?\DateTimeImmutable $closedAt;
    private ?\DateTimeImmutable $resolvedAt;

    private ?int $slaSnapshotId;
    private ?\DateTimeImmutable $responseDueAt;
    private ?\DateTimeImmutable $resolutionDueAt;
    private ?\DateTimeImmutable $respondedAt;

    // Backbone fields (C1)
    private string $primaryContainer = 'support';
    private ?int $projectId = null;
    private ?int $quoteId = null;
    private ?int $phaseId = null;
    private ?int $parentTicketId = null;
    private ?int $rootTicketId = null;
    private string $ticketKind = 'work';
    private ?int $departmentIdExt = null;
    private ?int $requiredRoleId = null;
    private ?string $skillLevel = null;
    private string $billingContextType = 'adhoc';
    private ?int $agreementId = null;
    private ?int $ratePlanId = null;
    private bool $isBillableDefault = true;
    private ?int $soldMinutes = null;
    private ?int $estimatedMinutes = null;
    private ?int $remainingMinutes = null;
    private bool $isRollup = false;
    private string $lifecycleOwner = 'support';
    private bool $isBaselineLocked = false;
    private ?int $changeOrderSourceTicketId = null;
    private ?int $soldValueCents = null;

    public function __construct(
        int $customerId,
        string $subject,
        string $description,
        string $status = 'new',
        string $priority = 'medium',
        ?int $siteId = null,
        ?int $slaId = null,
        ?int $id = null,
        ?int $malleableSchemaVersion = null,
        array $malleableData = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $openedAt = null,
        ?\DateTimeImmutable $closedAt = null,
        ?\DateTimeImmutable $resolvedAt = null,
        ?int $slaSnapshotId = null,
        ?\DateTimeImmutable $responseDueAt = null,
        ?\DateTimeImmutable $resolutionDueAt = null,
        ?\DateTimeImmutable $respondedAt = null,
        ?string $queueId = null,
        ?string $ownerUserId = null,
        ?string $category = null,
        ?string $subcategory = null,
        ?string $intakeSource = null,
        ?int $contactId = null,
        // Backbone fields (C1) — all optional for backward compat
        string $primaryContainer = 'support',
        ?int $projectId = null,
        ?int $quoteId = null,
        ?int $phaseId = null,
        ?int $parentTicketId = null,
        ?int $rootTicketId = null,
        string $ticketKind = 'work',
        ?int $departmentIdExt = null,
        ?int $requiredRoleId = null,
        ?string $skillLevel = null,
        string $billingContextType = 'adhoc',
        ?int $agreementId = null,
        ?int $ratePlanId = null,
        bool $isBillableDefault = true,
        ?int $soldMinutes = null,
        ?int $estimatedMinutes = null,
        ?int $remainingMinutes = null,
        bool $isRollup = false,
        string $lifecycleOwner = 'support',
        bool $isBaselineLocked = false,
        ?int $changeOrderSourceTicketId = null,
        ?int $soldValueCents = null
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->siteId = $siteId;
        $this->subject = $subject;
        $this->description = $description;
        $this->status = $status;
        $this->priority = $priority;
        $this->slaId = $slaId;
        $this->queueId = $queueId;
        $this->ownerUserId = $ownerUserId;
        $this->category = $category;
        $this->subcategory = $subcategory;
        $this->intakeSource = $intakeSource;
        $this->contactId = $contactId;
        $this->malleableSchemaVersion = $malleableSchemaVersion;
        $this->malleableData = $malleableData;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->openedAt = $openedAt ?? ($status !== 'new' ? new \DateTimeImmutable() : null);
        $this->closedAt = $closedAt;
        $this->resolvedAt = $resolvedAt;
        $this->slaSnapshotId = $slaSnapshotId;
        $this->responseDueAt = $responseDueAt;
        $this->resolutionDueAt = $resolutionDueAt;
        $this->respondedAt = $respondedAt;
        // Backbone
        $this->primaryContainer = $primaryContainer;
        $this->projectId = $projectId;
        $this->quoteId = $quoteId;
        $this->phaseId = $phaseId;
        $this->parentTicketId = $parentTicketId;
        $this->rootTicketId = $rootTicketId;
        $this->ticketKind = $ticketKind;
        $this->departmentIdExt = $departmentIdExt;
        $this->requiredRoleId = $requiredRoleId;
        $this->skillLevel = $skillLevel;
        $this->billingContextType = $billingContextType;
        $this->agreementId = $agreementId;
        $this->ratePlanId = $ratePlanId;
        $this->isBillableDefault = $isBillableDefault;
        $this->soldMinutes = $soldMinutes;
        $this->estimatedMinutes = $estimatedMinutes;
        $this->remainingMinutes = $remainingMinutes;
        $this->isRollup = $isRollup;
        $this->lifecycleOwner = $lifecycleOwner;
        $this->isBaselineLocked = $isBaselineLocked;
        $this->changeOrderSourceTicketId = $changeOrderSourceTicketId;
        $this->soldValueCents = $soldValueCents;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function siteId(): ?int
    {
        return $this->siteId;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function priority(): string
    {
        return $this->priority;
    }

    public function slaId(): ?int
    {
        return $this->slaId;
    }

    public function queueId(): ?string
    {
        return $this->queueId;
    }

    public function ownerUserId(): ?string
    {
        return $this->ownerUserId;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function subcategory(): ?string
    {
        return $this->subcategory;
    }

    public function intakeSource(): ?string
    {
        return $this->intakeSource;
    }

    public function contactId(): ?int
    {
        return $this->contactId;
    }

    public function malleableSchemaVersion(): ?int
    {
        return $this->malleableSchemaVersion;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function openedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function slaSnapshotId(): ?int
    {
        return $this->slaSnapshotId;
    }

    public function responseDueAt(): ?\DateTimeImmutable
    {
        return $this->responseDueAt;
    }

    public function resolutionDueAt(): ?\DateTimeImmutable
    {
        return $this->resolutionDueAt;
    }

    public function respondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function assignSla(
        int $slaSnapshotId,
        \DateTimeImmutable $responseDueAt,
        \DateTimeImmutable $resolutionDueAt
    ): void {
        $this->slaSnapshotId = $slaSnapshotId;
        $this->responseDueAt = $responseDueAt;
        $this->resolutionDueAt = $resolutionDueAt;
    }

    public function markAsResponded(\DateTimeImmutable $respondedAt): void
    {
        if ($this->respondedAt === null) {
            $this->respondedAt = $respondedAt;
        }
    }

    /**
     * Assign ticket to a team queue. Clears individual owner.
     */
    public function assignToTeam(string $queueId): void
    {
        $previousOwner = $this->ownerUserId;
        $previousQueue = $this->queueId;
        $this->queueId = $queueId;
        $this->ownerUserId = null;
        $this->recordEvent(new TicketAssigned($this, null, $previousOwner, $previousQueue, $queueId));
    }

    /**
     * Assign ticket to a specific employee. Preserves queue context.
     */
    public function assignToEmployee(string $employeeUserId): void
    {
        $previousOwner = $this->ownerUserId;
        $this->ownerUserId = $employeeUserId;
        $this->recordEvent(new TicketAssigned($this, $employeeUserId, $previousOwner, $this->queueId, $this->queueId));
    }

    /**
     * Pull ticket to self (self-assign). Alias for assignToEmployee with requesting user.
     */
    public function pull(string $requestingUserId): void
    {
        $this->assignToEmployee($requestingUserId);
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // Backbone getters
    public function primaryContainer(): string { return $this->primaryContainer; }
    public function projectId(): ?int { return $this->projectId; }
    public function quoteId(): ?int { return $this->quoteId; }
    public function phaseId(): ?int { return $this->phaseId; }
    public function parentTicketId(): ?int { return $this->parentTicketId; }
    public function rootTicketId(): ?int { return $this->rootTicketId; }
    public function ticketKind(): string { return $this->ticketKind; }
    public function departmentIdExt(): ?int { return $this->departmentIdExt; }
    public function requiredRoleId(): ?int { return $this->requiredRoleId; }
    public function skillLevel(): ?string { return $this->skillLevel; }
    public function billingContextType(): string { return $this->billingContextType; }
    public function agreementId(): ?int { return $this->agreementId; }
    public function ratePlanId(): ?int { return $this->ratePlanId; }
    public function isBillableDefault(): bool { return $this->isBillableDefault; }
    public function soldMinutes(): ?int { return $this->soldMinutes; }
    public function estimatedMinutes(): ?int { return $this->estimatedMinutes; }
    public function remainingMinutes(): ?int { return $this->remainingMinutes; }
    public function isRollup(): bool { return $this->isRollup; }
    public function lifecycleOwner(): string { return $this->lifecycleOwner; }
    public function isBaselineLocked(): bool { return $this->isBaselineLocked; }
    public function changeOrderSourceTicketId(): ?int { return $this->changeOrderSourceTicketId; }
    public function soldValueCents(): ?int { return $this->soldValueCents; }

    /**
     * Whether this ticket can accept time entries.
     *
     * Rules:
     * - Rollup tickets (WBS parents) cannot accept time entries directly.
     * - Only tickets in an executable lifecycle state (in_progress) may accept time.
     */
    public function canAcceptTimeEntries(): bool
    {
        return !$this->isRollup && $this->status === 'in_progress';
    }

    /**
     * Set root_ticket_id to self after insert (auto-increment ID not known at construction time).
     * Only callable when rootTicketId has not been set yet.
     */
    public function setRootTicketId(int $id): void
    {
        if ($this->rootTicketId !== null) {
            throw new \DomainException('root_ticket_id is already set and cannot be changed.');
        }
        $this->rootTicketId = $id;
    }

    /**
     * Derive the billable default from billing context type.
     * Used when creating tickets or when billing context changes.
     */
    public static function deriveBillableDefault(string $billingContextType): bool
    {
        return match ($billingContextType) {
            'agreement' => true,
            'project' => true,
            'adhoc' => true,
            'internal' => false,
            default => true,
        };
    }

    /**
     * Transition ticket status with lifecycle-governed validation.
     * Validates the transition is allowed for this ticket's lifecycle_owner,
     * sets timestamp side-effects, and records a TicketStatusChanged event.
     *
     * @throws \DomainException if the transition is not allowed
     */
    public function transitionStatus(string $newStatus): void
    {
        if ($newStatus === $this->status) {
            return; // No-op for same status
        }

        $currentVO = TicketStatus::fromString($this->status, $this->lifecycleOwner);

        if (!$currentVO->canTransitionTo($newStatus, $this->lifecycleOwner)) {
            $allowed = $currentVO->allowedTransitions($this->lifecycleOwner);
            throw new \DomainException(
                "Cannot transition ticket from '{$this->status}' to '$newStatus' "
                . "(lifecycle: {$this->lifecycleOwner}). "
                . "Allowed transitions: " . ($allowed ? implode(', ', $allowed) : 'none (terminal state)')
            );
        }

        $previousStatus = $this->status;
        $this->status = $newStatus;

        // Timestamp side-effects
        if ($previousStatus === 'new' && $newStatus !== 'new' && !$this->openedAt) {
            $this->openedAt = new \DateTimeImmutable();
        }

        if ($newStatus === 'resolved' && !$this->resolvedAt) {
            $this->resolvedAt = new \DateTimeImmutable();
        }

        if ($newStatus === 'closed') {
            $this->closedAt = new \DateTimeImmutable();
        } elseif ($previousStatus === 'closed') {
            // Reopening — clear closedAt (shouldn't happen per transition map, but defensive)
            $this->closedAt = null;
        }

        $this->recordEvent(new TicketStatusChanged($this, $previousStatus, $newStatus, $this->lifecycleOwner));
    }

    public function update(
        string $subject,
        string $description,
        string $priority,
        string $status,
        ?int $siteId,
        ?int $slaId,
        array $malleableData
    ): void {
        // Baseline-locked tickets: reject mutation of immutable commercial fields via malleable data
        if ($this->isBaselineLocked) {
            $immutableKeys = ['sold_minutes', 'sold_value_cents', 'sold_hours', 'is_baseline_locked'];
            foreach ($immutableKeys as $key) {
                if (array_key_exists($key, $malleableData)) {
                    throw new \DomainException(
                        "Cannot modify '$key' on a baseline-locked ticket (id: {$this->id})."
                    );
                }
            }
        }

        $this->subject = $subject;
        $this->description = $description;
        $this->priority = $priority;
        $this->siteId = $siteId;
        $this->slaId = $slaId;
        $this->malleableData = $malleableData;

        if ($status !== $this->status) {
            $this->transitionStatus($status);
        }
    }
}
