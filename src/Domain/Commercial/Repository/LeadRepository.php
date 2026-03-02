<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\Lead;

interface LeadRepository
{
    public function save(Lead $lead): void;
    public function findById(int $id): ?Lead;
    public function findAll(): array;
    public function findByCustomerId(int $customerId): array;
    public function delete(int $id): void;
}
