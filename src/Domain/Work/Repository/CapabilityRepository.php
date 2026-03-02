<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\Capability;

interface CapabilityRepository
{
    public function save(Capability $capability): void;
    public function findById(int $id): ?Capability;
    public function findAll(): array;
}
