<?php

declare(strict_types=1);

namespace Pet\Domain\Activity\Repository;

use Pet\Domain\Activity\Entity\ActivityLog;

interface ActivityLogRepository
{
    public function save(ActivityLog $log): void;
    public function findAll(int $limit = 50): array;
    public function findByRelatedEntity(string $entityType, int $entityId): array;
}
