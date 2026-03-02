<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Repository;

use Pet\Domain\Identity\Entity\Employee;

interface EmployeeRepository
{
    public function save(Employee $employee): void;

    public function findById(int $id): ?Employee;

    public function findByWpUserId(int $wpUserId): ?Employee;

    /**
     * @return Employee[]
     */
    public function findAll(): array;
}
