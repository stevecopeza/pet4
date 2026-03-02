<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\Role;

interface RoleRepository
{
    public function save(Role $role): void;
    public function findById(int $id): ?Role;
    public function findAll(): array;
    public function findByStatus(string $status): array;
}
