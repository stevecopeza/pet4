<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\PersonSkill;
use Pet\Domain\Work\Repository\PersonSkillRepository;

class SqlPersonSkillRepository implements PersonSkillRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_person_skills';
    }

    public function save(PersonSkill $personSkill): void
    {
        $data = [
            'employee_id' => $personSkill->employeeId(),
            'skill_id' => $personSkill->skillId(),
            'review_cycle_id' => $personSkill->reviewCycleId(),
            'self_rating' => $personSkill->selfRating(),
            'manager_rating' => $personSkill->managerRating(),
            'effective_date' => $personSkill->effectiveDate()->format('Y-m-d'),
            'created_at' => $personSkill->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%d', '%d', '%d', '%d', '%s', '%s'];

        if ($personSkill->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $personSkill->id()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
        }
    }

    public function findById(int $id): ?PersonSkill
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
            "SELECT * FROM {$this->tableName} WHERE employee_id = %d ORDER BY effective_date DESC",
            $employeeId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findBySkillId(int $skillId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE skill_id = %d ORDER BY effective_date DESC",
            $skillId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByEmployeeAndSkill(int $employeeId, int $skillId): ?PersonSkill
    {
        // Get the latest one
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE employee_id = %d AND skill_id = %d ORDER BY effective_date DESC LIMIT 1",
            $employeeId,
            $skillId
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByReviewCycleId(int $reviewCycleId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE review_cycle_id = %d ORDER BY skill_id ASC",
            $reviewCycleId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function getAverageProficiencyBySkill(): array
    {
        $skillsTable = $this->wpdb->prefix . 'pet_skills';
        $sql = "
            SELECT 
                s.name as skill_name, 
                AVG(ps.manager_rating) as avg_rating 
            FROM {$this->tableName} ps
            JOIN {$skillsTable} s ON s.id = ps.skill_id
            WHERE ps.manager_rating IS NOT NULL
            GROUP BY ps.skill_id 
            ORDER BY avg_rating DESC
            LIMIT 10
        ";
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    private function hydrate(object $row): PersonSkill
    {
        return new PersonSkill(
            (int) $row->employee_id,
            (int) $row->skill_id,
            (int) ($row->self_rating ?? 0),
            (int) ($row->manager_rating ?? 0),
            new \DateTimeImmutable($row->effective_date),
            $row->review_cycle_id !== null ? (int) $row->review_cycle_id : null,
            (int) $row->id,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
