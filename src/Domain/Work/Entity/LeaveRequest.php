<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

final class LeaveRequest
{
    private int $id;
    private string $uuid;
    private int $employeeId;
    private int $leaveTypeId;
    private \DateTimeImmutable $startDate;
    private \DateTimeImmutable $endDate;
    private string $status;
    private ?int $decidedByEmployeeId;
    private ?\DateTimeImmutable $decidedAt;
    private ?string $decisionReason;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        int $id,
        string $uuid,
        int $employeeId,
        int $leaveTypeId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        string $status,
        ?int $decidedByEmployeeId,
        ?\DateTimeImmutable $decidedAt,
        ?string $decisionReason,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->uuid = $uuid;
        $this->employeeId = $employeeId;
        $this->leaveTypeId = $leaveTypeId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
        $this->decidedByEmployeeId = $decidedByEmployeeId;
        $this->decidedAt = $decidedAt;
        $this->decisionReason = $decisionReason;
        $this->notes = $notes;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function draft(
        string $uuid,
        int $employeeId,
        int $leaveTypeId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?string $notes
    ): self {
        $now = new \DateTimeImmutable();
        return new self(0, $uuid, $employeeId, $leaveTypeId, $startDate, $endDate, 'draft', null, null, null, $notes, $now, $now);
    }

    public function id(): int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function uuid(): string { return $this->uuid; }
    public function employeeId(): int { return $this->employeeId; }
    public function leaveTypeId(): int { return $this->leaveTypeId; }
    public function startDate(): \DateTimeImmutable { return $this->startDate; }
    public function endDate(): \DateTimeImmutable { return $this->endDate; }
    public function status(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function decidedByEmployeeId(): ?int { return $this->decidedByEmployeeId; }
    public function decidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function decisionReason(): ?string { return $this->decisionReason; }
    public function notes(): ?string { return $this->notes; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}

