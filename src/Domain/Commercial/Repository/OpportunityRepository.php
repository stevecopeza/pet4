<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\Opportunity;

interface OpportunityRepository
{
    public function save(Opportunity $opportunity): void;

    public function findById(string $id): ?Opportunity;

    /** @return Opportunity[] */
    public function findAll(): array;

    /** @return Opportunity[] */
    public function findByCustomerId(int $customerId): array;

    /** @return Opportunity[] */
    public function findByStage(string $stage): array;

    /** @return Opportunity[] */
    public function findOpen(): array;

    /** @return array<mixed> enriched rows with customer_name */
    public function findAllEnriched(): array;

    public function delete(string $id): void;
}
