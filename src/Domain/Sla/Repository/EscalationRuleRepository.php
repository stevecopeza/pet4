<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Repository;

use Pet\Domain\Sla\Entity\EscalationRule;

interface EscalationRuleRepository
{
    /**
     * @return EscalationRule[]
     */
    public function findAll(int $limit = 20, int $offset = 0): array;

    public function findById(int $id): ?EscalationRule;

    /**
     * @param EscalationRule $rule
     * @param int|null $slaId Required for new rules
     */
    public function save(EscalationRule $rule, ?int $slaId = null): void;

    public function enable(int $id): void;

    public function disable(int $id): void;

    public function getDashboardStats(): array;
}
