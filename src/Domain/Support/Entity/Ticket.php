<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Entity;

class Ticket
{
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
        ?int $contactId = null
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

    public function update(
        string $subject,
        string $description,
        string $priority,
        string $status,
        ?int $siteId,
        ?int $slaId,
        array $malleableData
    ): void {
        $this->subject = $subject;
        $this->description = $description;
        $this->priority = $priority;
        $this->siteId = $siteId;
        $this->slaId = $slaId;
        $this->malleableData = $malleableData;

        // Status transition logic could be here, but for now simple assignment
        // If transitioning to closed, set closedAt
        if ($status === 'closed' && $this->status !== 'closed') {
            $this->closedAt = new \DateTimeImmutable();
        } elseif ($status !== 'closed') {
            $this->closedAt = null;
        }
        
        // If transitioning from new to something else, ensure openedAt is set
        if ($this->status === 'new' && $status !== 'new' && !$this->openedAt) {
            $this->openedAt = new \DateTimeImmutable();
        }

        $this->status = $status;
    }
}
