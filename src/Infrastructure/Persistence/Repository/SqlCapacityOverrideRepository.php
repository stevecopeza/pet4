<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\CapacityOverride;
use Pet\Domain\Work\Repository\CapacityOverrideRepository;

final class SqlCapacityOverrideRepository implements CapacityOverrideRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function setOverride(int $employeeId, \DateTimeImmutable $date, int $capacityPct, ?string $reason): void
    {
        $table = $this->wpdb->prefix . 'pet_capacity_overrides';
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM $table WHERE employee_id = %d AND effective_date = %s", $employeeId, $date->format('Y-m-d'))
        );
        if ($exists) {
            $this->wpdb->update($table, [
                'capacity_pct' => $capacityPct,
                'reason' => $reason,
            ], [
                'employee_id' => $employeeId,
                'effective_date' => $date->format('Y-m-d'),
            ]);
            return;
        }
        $this->wpdb->insert($table, [
            'employee_id' => $employeeId,
            'effective_date' => $date->format('Y-m-d'),
            'capacity_pct' => $capacityPct,
            'reason' => $reason,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function findForDate(int $employeeId, \DateTimeImmutable $date): ?CapacityOverride
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}pet_capacity_overrides WHERE employee_id = %d AND effective_date = %s LIMIT 1",
                $employeeId,
                $date->format('Y-m-d')
            ),
            ARRAY_A
        );
        if (!$row) return null;
        return new CapacityOverride(
            (int)$row['id'],
            (int)$row['employee_id'],
            new \DateTimeImmutable($row['effective_date']),
            (int)$row['capacity_pct'],
            $row['reason'] ?: null,
            new \DateTimeImmutable($row['created_at'])
        );
    }
}

