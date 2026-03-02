<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\Role;
use Pet\Domain\Work\Repository\RoleRepository;

class SqlRoleRepository implements RoleRepository
{
    private $wpdb;
    private $tableName;
    private $roleSkillsTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_roles';
        $this->roleSkillsTable = $wpdb->prefix . 'pet_role_skills';
    }

    public function save(Role $role): void
    {
        $data = [
            'name' => $role->name(),
            'version' => $role->version(),
            'status' => $role->status(),
            'level' => $role->level(),
            'description' => $role->description(),
            'success_criteria' => $role->successCriteria(),
            'created_at' => $role->createdAt()->format('Y-m-d H:i:s'),
            'published_at' => $role->publishedAt() ? $role->publishedAt()->format('Y-m-d H:i:s') : null,
        ];

        $format = ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($role->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $role->id()],
                $format,
                ['%d']
            );
            $roleId = $role->id();
        } else {
            $insertResult = $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            $roleId = (int)$this->wpdb->insert_id;

            if ($insertResult === false || $roleId <= 0) {
                $error = $this->wpdb->last_error ?: 'unknown error';
                throw new \RuntimeException('SqlRoleRepository failed to insert role: ' . $error);
            }
        }

        $this->updateRoleSkills($roleId, $role->requiredSkills());

        // Set generated ID back on the entity to satisfy handlers expecting int return
        if (!$role->id() && $roleId) {
            $ref = new \ReflectionObject($role);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                $prop->setValue($role, (int)$roleId);
            }
        }
    }

    private function updateRoleSkills(int $roleId, array $skills): void
    {
        $this->wpdb->delete($this->roleSkillsTable, ['role_id' => $roleId], ['%d']);

        foreach ($skills as $skillId => $details) {
            $this->wpdb->insert(
                $this->roleSkillsTable,
                [
                    'role_id' => $roleId,
                    'skill_id' => $skillId,
                    'min_proficiency_level' => $details['min_proficiency_level'],
                    'importance_weight' => $details['importance_weight'] ?? 1,
                ],
                ['%d', '%d', '%d', '%d']
            );
        }
    }

    public function findById(int $id): ?Role
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->tableName} ORDER BY name ASC, version DESC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByStatus(string $status): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE status = %s ORDER BY name ASC",
            $status
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    private function hydrate(object $row): Role
    {
        $requiredSkills = $this->getRequiredSkills((int) $row->id);

        return new Role(
            $row->name,
            $row->level,
            $row->description,
            $row->success_criteria,
            (int) $row->id,
            (int) $row->version,
            $row->status,
            $requiredSkills,
            new \DateTimeImmutable($row->created_at),
            $row->published_at ? new \DateTimeImmutable($row->published_at) : null
        );
    }

    private function getRequiredSkills(int $roleId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT skill_id, min_proficiency_level, importance_weight FROM {$this->roleSkillsTable} WHERE role_id = %d",
            $roleId
        );
        $results = $this->wpdb->get_results($sql);

        $skills = [];
        foreach ($results as $row) {
            $skills[$row->skill_id] = [
                'min_proficiency_level' => (int) $row->min_proficiency_level,
                'importance_weight' => (int) $row->importance_weight,
            ];
        }
        return $skills;
    }
}
