<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\PersonSkill;

interface PersonSkillRepository
{
    public function save(PersonSkill $personSkill): void;
    public function findById(int $id): ?PersonSkill;
    public function findByEmployeeId(int $employeeId): array;
    public function findBySkillId(int $skillId): array;
    public function findByEmployeeAndSkill(int $employeeId, int $skillId): ?PersonSkill;
    public function findByReviewCycleId(int $reviewCycleId): array;
    public function getAverageProficiencyBySkill(): array;
}
