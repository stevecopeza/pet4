<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\LeaveType;

interface LeaveTypeRepository
{
    public function findAll(): array;
    public function findById(int $id): ?LeaveType;
}

