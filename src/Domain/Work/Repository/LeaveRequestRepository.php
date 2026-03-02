<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\LeaveRequest;

interface LeaveRequestRepository
{
    public function save(LeaveRequest $req): void;
    public function findById(int $id): ?LeaveRequest;
    public function findByEmployee(int $employeeId, int $limit = 50): array;
    public function setStatus(int $id, string $status, ?int $decidedByEmployeeId = null, ?\DateTimeImmutable $decidedAt = null, ?string $reason = null): void;
    public function isApprovedOnDate(int $employeeId, \DateTimeImmutable $date): bool;
}

