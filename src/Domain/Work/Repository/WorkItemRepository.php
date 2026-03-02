<?php

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\WorkItem;

interface WorkItemRepository
{
    public function save(WorkItem $workItem): void;
    public function findById(string $id): ?WorkItem;
    public function findBySource(string $sourceType, string $sourceId): ?WorkItem;
    public function findByAssignedUser(string $userId): array;
    public function findByDepartmentUnassigned(string $departmentId): array;
    public function findActive(): array;
    public function findAll(): array;
}
