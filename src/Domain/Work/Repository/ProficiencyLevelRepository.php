<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\ProficiencyLevel;

interface ProficiencyLevelRepository
{
    public function save(ProficiencyLevel $level): void;
    public function findById(int $id): ?ProficiencyLevel;
    public function findAll(): array;
}
