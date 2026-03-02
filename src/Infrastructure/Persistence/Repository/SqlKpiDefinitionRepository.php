<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\KpiDefinition;
use Pet\Domain\Work\Repository\KpiDefinitionRepository;

class SqlKpiDefinitionRepository implements KpiDefinitionRepository
{
    private $wpdb;
    private $table;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'pet_kpi_definitions';
    }

    public function save(KpiDefinition $definition): void
    {
        $data = [
            'name' => $definition->name(),
            'description' => $definition->description(),
            'default_frequency' => $definition->defaultFrequency(),
            'unit' => $definition->unit(),
            'created_at' => $definition->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%s', '%s', '%s', '%s', '%s'];

        if ($definition->id()) {
            $this->wpdb->update(
                $this->table,
                $data,
                ['id' => $definition->id()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert(
                $this->table,
                $data,
                $format
            );
        }
    }

    public function findById(int $id): ?KpiDefinition
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY name ASC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function delete(int $id): void
    {
        $this->wpdb->delete($this->table, ['id' => $id], ['%d']);
    }

    private function hydrate(object $row): KpiDefinition
    {
        return new KpiDefinition(
            $row->name,
            $row->description,
            $row->default_frequency,
            $row->unit,
            (int) $row->id,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
