<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\RoleKpi;
use Pet\Domain\Work\Repository\RoleKpiRepository;

class SqlRoleKpiRepository implements RoleKpiRepository
{
    private $wpdb;
    private $table;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'pet_role_kpis';
    }

    public function save(RoleKpi $roleKpi): void
    {
        $data = [
            'role_id' => $roleKpi->roleId(),
            'kpi_definition_id' => $roleKpi->kpiDefinitionId(),
            'weight_percentage' => $roleKpi->weightPercentage(),
            'target_value' => $roleKpi->targetValue(),
            'measurement_frequency' => $roleKpi->measurementFrequency(),
            'created_at' => $roleKpi->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%d', '%d', '%f', '%s', '%s'];

        if ($roleKpi->id()) {
            $this->wpdb->update(
                $this->table,
                $data,
                ['id' => $roleKpi->id()],
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

    public function findByRoleId(int $roleId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE role_id = %d",
            $roleId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function delete(int $id): void
    {
        $this->wpdb->delete($this->table, ['id' => $id], ['%d']);
    }

    private function hydrate(object $row): RoleKpi
    {
        return new RoleKpi(
            (int) $row->role_id,
            (int) $row->kpi_definition_id,
            (int) $row->weight_percentage,
            (float) $row->target_value,
            $row->measurement_frequency,
            (int) $row->id,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
