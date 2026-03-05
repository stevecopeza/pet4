<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Repository\RoleTeamRepository;

class SqlRoleTeamRepository implements RoleTeamRepository
{
    private $wpdb;
    private string $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_role_teams';
    }

    public function findByRoleId(int $roleId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE role_id = %d ORDER BY is_primary DESC, team_id ASC",
            $roleId
        );
        $rows = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function replaceForRole(int $roleId, array $mappings): void
    {
        // Enforce single primary
        $primaryCount = 0;
        foreach ($mappings as $m) {
            if (!empty($m['is_primary'])) {
                $primaryCount++;
            }
        }
        if ($primaryCount > 1) {
            throw new \InvalidArgumentException('At most one team may be marked as primary per role.');
        }

        // Delete existing mappings
        $this->wpdb->delete($this->tableName, ['role_id' => $roleId], ['%d']);

        // Insert new set
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($mappings as $m) {
            $this->wpdb->insert($this->tableName, [
                'role_id'    => $roleId,
                'team_id'    => (int)$m['team_id'],
                'is_primary' => !empty($m['is_primary']) ? 1 : 0,
                'created_at' => $now,
            ], ['%d', '%d', '%d', '%s']);
        }
    }

    public function findByTeamId(int $teamId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE team_id = %d ORDER BY is_primary DESC, role_id ASC",
            $teamId
        );
        $rows = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(object $row): array
    {
        return [
            'id'         => (int)$row->id,
            'role_id'    => (int)$row->role_id,
            'team_id'    => (int)$row->team_id,
            'is_primary' => (bool)$row->is_primary,
            'created_at' => $row->created_at,
        ];
    }
}
