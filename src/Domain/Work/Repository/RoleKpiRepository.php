<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\RoleKpi;

interface RoleKpiRepository
{
    public function save(RoleKpi $roleKpi): void;
    public function findByRoleId(int $roleId): array;
    public function delete(int $id): void;
}
