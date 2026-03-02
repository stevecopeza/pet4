<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\Assignment;
use Pet\Domain\Work\Repository\AssignmentRepository;

class SqlAssignmentRepository implements AssignmentRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_person_role_assignments';
    }

    public function save(Assignment $assignment): void
    {
        $data = [
            'employee_id' => $assignment->employeeId(),
            'role_id' => $assignment->roleId(),
            'start_date' => $assignment->startDate()->format('Y-m-d'),
            'end_date' => $assignment->endDate() ? $assignment->endDate()->format('Y-m-d') : null,
            'allocation_pct' => $assignment->allocationPct(),
            'status' => $assignment->status(),
            'created_at' => $assignment->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%d', '%s', '%s', '%d', '%s', '%s'];

        if ($assignment->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $assignment->id()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            $insertId = (int)$this->wpdb->insert_id;
            if ($insertId > 0) {
                $ref = new \ReflectionObject($assignment);
                if ($ref->hasProperty('id')) {
                    $prop = $ref->getProperty('id');
                    $prop->setAccessible(true);
                    $prop->setValue($assignment, $insertId);
                }
            }
        }
    }

    public function findById(int $id): ?Assignment
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmployeeId(int $employeeId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE employee_id = %d ORDER BY start_date DESC",
            $employeeId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByRoleId(int $roleId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE role_id = %d ORDER BY start_date DESC",
            $roleId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->tableName} ORDER BY start_date DESC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    private function hydrate(object $row): Assignment
    {
        return new Assignment(
            (int) $row->employee_id,
            (int) $row->role_id,
            new \DateTimeImmutable($row->start_date),
            (int) $row->id,
            $row->end_date ? new \DateTimeImmutable($row->end_date) : null,
            (int) $row->allocation_pct,
            $row->status,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
