<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Repository;

use Pet\Domain\Delivery\Entity\Project;

interface ProjectRepository
{
    public function save(Project $project): void;

    public function findById(int $id): ?Project;

    /**
     * @return Project[]
     */
    public function findAll(): array;

    /**
     * @return Project[]
     */
    public function findByCustomerId(int $customerId): array;

    public function findByQuoteId(int $quoteId): ?Project;

    public function countActive(): int;

    public function sumSoldHours(): float;
}
