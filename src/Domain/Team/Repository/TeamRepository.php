<?php

declare(strict_types=1);

namespace Pet\Domain\Team\Repository;

use Pet\Domain\Team\Entity\Team;

interface TeamRepository
{
    public function find(int $id): ?Team;
    public function findAll(bool $includeArchived = false): array;
    public function save(Team $team): void;
    public function delete(int $id): void;
    public function findByParent(int $parentId): array;
}
