<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\ProficiencyLevel;
use Pet\Domain\Work\Repository\ProficiencyLevelRepository;

class SqlProficiencyLevelRepository implements ProficiencyLevelRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_proficiency_levels';
    }

    public function save(ProficiencyLevel $level): void
    {
        $data = [
            'level_number' => $level->levelNumber(),
            'name' => $level->name(),
            'definition' => $level->definition(),
            'created_at' => $level->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%s', '%s', '%s'];

        if ($level->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $level->id()],
                $format,
                ['%d']
            );
        } else {
            $sql = "
                INSERT INTO {$this->tableName} (level_number, name, definition, created_at)
                VALUES (%d, %s, %s, %s)
                ON DUPLICATE KEY UPDATE name = VALUES(name), definition = VALUES(definition)
            ";
            $prepared = $this->wpdb->prepare(
                $sql,
                $level->levelNumber(),
                $level->name(),
                $level->definition(),
                $level->createdAt()->format('Y-m-d H:i:s')
            );
            $this->wpdb->query($prepared);
        }
    }

    public function findById(int $id): ?ProficiencyLevel
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
        $sql = "SELECT * FROM {$this->tableName} ORDER BY level_number ASC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    private function hydrate(object $row): ProficiencyLevel
    {
        return new ProficiencyLevel(
            (int) $row->level_number,
            $row->name,
            $row->definition,
            (int) $row->id,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
