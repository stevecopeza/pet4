<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\Assignment;

interface AssignmentRepository
{
    public function save(Assignment $assignment): void;
    public function findById(int $id): ?Assignment;
    public function findByEmployeeId(int $employeeId): array;
    public function findByRoleId(int $roleId): array;
    public function findAll(): array;
}
