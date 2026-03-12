<?php

declare(strict_types=1);

namespace Pet\Domain\Escalation\Repository;

use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Entity\EscalationTransition;

interface EscalationRepository
{
    public function save(Escalation $escalation): void;
    public function findById(int $id): ?Escalation;
    public function findByEscalationId(string $escalationId): ?Escalation;
    public function findBySourceEntity(string $sourceEntityType, int $sourceEntityId): array;
    public function findOpen(): array;
    public function findOpenByDedupeKey(string $dedupeKey): ?Escalation;
    public function findAll(int $limit = 100, int $offset = 0): array;
    public function count(): int;

    public function saveTransition(EscalationTransition $transition): void;
    public function findTransitionsByEscalationId(int $escalationId): array;
}
