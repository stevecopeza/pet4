<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

interface RoleTeamRepository
{
    /**
     * Returns team mappings for a role, ordered with is_primary first.
     *
     * @return array<int, array{id: int, role_id: int, team_id: int, is_primary: bool, created_at: string}>
     */
    public function findByRoleId(int $roleId): array;

    /**
     * Replaces the full set of team mappings for a role.
     * Enforces at most one is_primary = true per role.
     *
     * @param array<int, array{team_id: int, is_primary: bool}> $mappings
     */
    public function replaceForRole(int $roleId, array $mappings): void;

    /**
     * Returns role mappings for a team.
     *
     * @return array<int, array{id: int, role_id: int, team_id: int, is_primary: bool, created_at: string}>
     */
    public function findByTeamId(int $teamId): array;
}
