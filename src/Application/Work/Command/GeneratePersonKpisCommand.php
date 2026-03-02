<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class GeneratePersonKpisCommand
{
    private int $employeeId;
    private int $roleId;
    private \DateTimeImmutable $periodStart;
    private \DateTimeImmutable $periodEnd;

    public function __construct(
        int $employeeId,
        int $roleId,
        string $periodStart,
        string $periodEnd
    ) {
        $this->employeeId = $employeeId;
        $this->roleId = $roleId;
        $this->periodStart = new \DateTimeImmutable($periodStart);
        $this->periodEnd = new \DateTimeImmutable($periodEnd);
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function periodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }
}
