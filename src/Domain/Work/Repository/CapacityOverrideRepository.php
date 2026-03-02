<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\CapacityOverride;

interface CapacityOverrideRepository
{
    public function setOverride(int $employeeId, \DateTimeImmutable $date, int $capacityPct, ?string $reason): void;
    public function findForDate(int $employeeId, \DateTimeImmutable $date): ?CapacityOverride;
}

