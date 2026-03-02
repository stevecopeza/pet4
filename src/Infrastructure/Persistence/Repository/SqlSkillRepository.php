<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\Skill;
use Pet\Domain\Work\Repository\SkillRepository;

class SqlSkillRepository implements SkillRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_skills';
    }

    public function save(Skill $skill): void
    {
        $data = [
            'capability_id' => $skill->capabilityId(),
            'name' => $skill->name(),
            'description' => $skill->description(),
            'status' => $skill->status(),
            'created_at' => $skill->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%s', '%s', '%s', '%s'];

        if ($skill->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $skill->id()],
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

    public function findById(int $id): ?Skill
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
        $sql = "SELECT * FROM {$this->tableName} ORDER BY name ASC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByCapabilityId(int $capabilityId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE capability_id = %d ORDER BY name ASC",
            $capabilityId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    private function hydrate(object $row): Skill
    {
        return new Skill(
            (int) $row->capability_id,
            $row->name,
            $row->description,
            (int) $row->id,
            $row->status,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
