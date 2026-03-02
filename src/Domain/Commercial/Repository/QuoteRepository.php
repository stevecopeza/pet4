<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\Quote;

interface QuoteRepository
{
    public function save(Quote $quote): void;

    public function findById(int $id, bool $lock = false): ?Quote;

    /**
     * @return Quote[]
     */
    public function findByCustomerId(int $customerId): array;

    /**
     * @return Quote[]
     */
    public function findAll(): array;

    public function countPending(): int;

    public function sumRevenue(\DateTimeImmutable $start, \DateTimeImmutable $end): float;

    public function delete(int $id): void;
}
