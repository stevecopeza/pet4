<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\Skill;

interface SkillRepository
{
    public function save(Skill $skill): void;
    public function findById(int $id): ?Skill;
    public function findAll(): array;
    public function findByCapabilityId(int $capabilityId): array;
}
