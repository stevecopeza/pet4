<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\PersonKpi;

interface PersonKpiRepository
{
    public function save(PersonKpi $personKpi): void;
    public function findById(int $id): ?PersonKpi;
    public function findByEmployeeId(int $employeeId): array;
    public function findByRoleId(int $roleId): array;
    public function findByPeriod(\DateTimeImmutable $start, \DateTimeImmutable $end): array;
    public function findByEmployeeAndPeriod(int $employeeId, \DateTimeImmutable $start, \DateTimeImmutable $end): array;
    public function getAverageAchievementByKpi(): array;
}
