<?php

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\DepartmentQueue;

interface DepartmentQueueRepository
{
    public function save(DepartmentQueue $queueItem): void;
    public function findById(string $id): ?DepartmentQueue;
    public function findByWorkItemId(string $workItemId): ?DepartmentQueue;
    public function findUnassignedByDepartment(string $departmentId): array;
}
