<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\PersonKpi;
use Pet\Domain\Work\Repository\PersonKpiRepository;

class SqlPersonKpiRepository implements PersonKpiRepository
{
    private $wpdb;
    private $table;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'pet_person_kpis';
    }

    public function save(PersonKpi $personKpi): void
    {
        $data = [
            'employee_id' => $personKpi->employeeId(),
            'kpi_definition_id' => $personKpi->kpiDefinitionId(),
            'role_id' => $personKpi->roleId(),
            'period_start' => $personKpi->periodStart()->format('Y-m-d'),
            'period_end' => $personKpi->periodEnd()->format('Y-m-d'),
            'target_value' => $personKpi->targetValue(),
            'actual_value' => $personKpi->actualValue(),
            'score' => $personKpi->score(),
            'status' => $personKpi->status(),
            'created_at' => $personKpi->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%s'];

        if ($personKpi->id()) {
            $this->wpdb->update(
                $this->table,
                $data,
                ['id' => $personKpi->id()],
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

    public function findById(int $id): ?PersonKpi
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmployeeId(int $employeeId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE employee_id = %d ORDER BY period_end DESC",
            $employeeId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByRoleId(int $roleId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE role_id = %d ORDER BY period_end DESC",
            $roleId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByPeriod(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE period_start >= %s AND period_end <= %s",
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByEmployeeAndPeriod(int $employeeId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE employee_id = %d AND period_start >= %s AND period_end <= %s",
            $employeeId,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function getAverageAchievementByKpi(): array
    {
        $kpiTable = $this->wpdb->prefix . 'pet_kpi_definitions';
        $sql = "
            SELECT 
                kd.name as kpi_name, 
                AVG(pk.score) as avg_score 
            FROM {$this->table} pk
            JOIN {$kpiTable} kd ON kd.id = pk.kpi_definition_id
            WHERE pk.score IS NOT NULL
            GROUP BY pk.kpi_definition_id
            ORDER BY avg_score DESC
            LIMIT 10
        ";
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    private function hydrate(object $row): PersonKpi
    {
        $personKpi = new PersonKpi(
            (int)$row->employee_id,
            (int)$row->kpi_definition_id,
            (int)$row->role_id,
            new \DateTimeImmutable($row->period_start),
            new \DateTimeImmutable($row->period_end),
            (float)$row->target_value,
            $row->actual_value ? (float)$row->actual_value : null
        );

        if ($row->score) {
            $personKpi->updateScore((float)$row->score);
        }
        
        if ($row->status) {
            $ref = new \ReflectionClass($personKpi);
            $statusProp = $ref->getProperty('status');
            $statusProp->setAccessible(true);
            $statusProp->setValue($personKpi, $row->status);
        }

        // Reflection to set ID and created_at
        $ref = new \ReflectionClass($personKpi);
        
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($personKpi, (int)$row->id);

        $createdAtProp = $ref->getProperty('createdAt');
        $createdAtProp->setAccessible(true);
        $createdAtProp->setValue($personKpi, new \DateTimeImmutable($row->created_at));

        return $personKpi;
    }
}
