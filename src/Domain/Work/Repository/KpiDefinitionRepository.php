<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\KpiDefinition;

interface KpiDefinitionRepository
{
    public function save(KpiDefinition $definition): void;
    public function findById(int $id): ?KpiDefinition;
    public function findAll(): array;
    public function delete(int $id): void;
}
