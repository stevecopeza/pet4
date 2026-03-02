<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Team\Entity\Team;
use Pet\Domain\Team\Repository\TeamRepository;

class SqlTeamRepository implements TeamRepository
{
    private $wpdb;
    private $tableName;
    private $membersTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_teams';
        $this->membersTable = $wpdb->prefix . 'pet_team_members';
    }

    public function find(int $id): ?Team
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(bool $includeArchived = false): array
    {
        $sql = "SELECT * FROM {$this->tableName}";
        if (!$includeArchived) {
            $sql .= " WHERE archived_at IS NULL";
        }
        $sql .= " ORDER BY name ASC";
        
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByParent(int $parentId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE parent_team_id = %d AND archived_at IS NULL ORDER BY name ASC",
            $parentId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function save(Team $team): void
    {
        $data = [
            'name' => $team->name(),
            'parent_team_id' => $team->parentTeamId(),
            'manager_id' => $team->managerId(),
            'escalation_manager_id' => $team->escalationManagerId(),
            'status' => $team->status(),
            'visual_type' => $team->visualType(),
            'visual_ref' => $team->visualRef(),
            'visual_version' => $team->visualVersion(),
            'visual_updated_at' => $team->visualUpdatedAt() ? $team->visualUpdatedAt()->format('Y-m-d H:i:s') : null,
            'created_at' => $team->createdAt()->format('Y-m-d H:i:s'),
            'archived_at' => $team->archivedAt() ? $team->archivedAt()->format('Y-m-d H:i:s') : null,
        ];

        $format = ['%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];

        if ($team->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $team->id()],
                $format,
                ['%d']
            );
            $teamId = $team->id();
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            $teamId = $this->wpdb->insert_id;
        }

        $this->updateMembers($teamId, $team->memberIds());
    }

    public function delete(int $id): void
    {
        // Soft delete
        $this->wpdb->update(
            $this->tableName,
            ['archived_at' => current_time('mysql')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    private function hydrate(object $row): Team
    {
        $memberIds = $this->getMemberIds((int) $row->id);

        return new Team(
            $row->name,
            (int) $row->id,
            !empty($row->parent_team_id) ? (int) $row->parent_team_id : null,
            !empty($row->manager_id) ? (int) $row->manager_id : null,
            !empty($row->escalation_manager_id) ? (int) $row->escalation_manager_id : null,
            isset($row->status) ? $row->status : 'active',
            $row->visual_type,
            $row->visual_ref,
            (int) ($row->visual_version ?? 1),
            !empty($row->visual_updated_at) ? new \DateTimeImmutable($row->visual_updated_at) : null,
            $memberIds,
            !empty($row->created_at) ? new \DateTimeImmutable($row->created_at) : null,
            !empty($row->archived_at) ? new \DateTimeImmutable($row->archived_at) : null
        );
    }

    private function getMemberIds(int $teamId): array
    {
        return array_map('intval', $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT employee_id FROM {$this->membersTable} WHERE team_id = %d AND removed_at IS NULL",
            $teamId
        )));
    }

    private function updateMembers(int $teamId, array $memberIds): void
    {
        // Get existing members
        $existing = $this->getMemberIds($teamId);
        
        // Determine additions and removals
        $toAdd = array_diff($memberIds, $existing);
        $toRemove = array_diff($existing, $memberIds);
        
        // Add new members
        foreach ($toAdd as $employeeId) {
            // Check if record exists (soft deleted or just history)
             $recordId = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->membersTable} WHERE team_id = %d AND employee_id = %d",
                $teamId,
                $employeeId
            ));

            if ($recordId) {
                 $this->wpdb->update(
                    $this->membersTable,
                    ['removed_at' => null, 'role' => 'member'],
                    ['id' => $recordId]
                );
            } else {
                $this->wpdb->insert(
                    $this->membersTable,
                    [
                        'team_id' => $teamId,
                        'employee_id' => $employeeId,
                        'role' => 'member',
                        'assigned_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%s']
                );
            }
        }
        
        // Remove members
        if (!empty($toRemove)) {
            // Can't use prepare with array directly for IN clause easily without placeholder generation
            $placeholders = implode(',', array_fill(0, count($toRemove), '%d'));
            $sql = $this->wpdb->prepare(
                "UPDATE {$this->membersTable} SET removed_at = %s WHERE team_id = %d AND employee_id IN ($placeholders)",
                array_merge([current_time('mysql'), $teamId], $toRemove)
            );
            $this->wpdb->query($sql);
        }
    }
}
