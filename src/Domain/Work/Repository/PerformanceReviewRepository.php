<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\PerformanceReview;

interface PerformanceReviewRepository
{
    public function save(PerformanceReview $review): int;
    public function findById(int $id): ?PerformanceReview;
    public function findByEmployeeId(int $employeeId): array;
    public function findByReviewerId(int $reviewerId): array;
}
