<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class AssignRoleToPersonCommand
{
    private int $employeeId;
    private int $roleId;
    private \DateTimeImmutable $startDate;
    private int $allocationPct;

    public function __construct(
        int $employeeId,
        int $roleId,
        string $startDate,
        int $allocationPct = 100
    ) {
        $this->employeeId = $employeeId;
        $this->roleId = $roleId;
        $this->startDate = new \DateTimeImmutable($startDate);
        $this->allocationPct = $allocationPct;
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function startDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function allocationPct(): int
    {
        return $this->allocationPct;
    }
}
