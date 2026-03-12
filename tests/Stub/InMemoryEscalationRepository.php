<?php

declare(strict_types=1);

namespace Pet\Tests\Stub;

use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Entity\EscalationTransition;
use Pet\Domain\Escalation\Repository\EscalationRepository;

class InMemoryEscalationRepository implements EscalationRepository
{
    /** @var Escalation[] */
    private array $escalations = [];
    /** @var EscalationTransition[] */
    private array $transitions = [];
    private int $nextId = 1;

    public function save(Escalation $escalation): void
    {
        if ($escalation->id() === null) {
            $ref = new \ReflectionObject($escalation);
            $prop = $ref->getProperty('id');
            $prop->setAccessible(true);
            $prop->setValue($escalation, $this->nextId++);
        }
        $this->escalations[$escalation->id()] = $escalation;
    }

    public function findById(int $id): ?Escalation
    {
        return $this->escalations[$id] ?? null;
    }

    public function findByEscalationId(string $escalationId): ?Escalation
    {
        foreach ($this->escalations as $esc) {
            if ($esc->escalationId() === $escalationId) {
                return $esc;
            }
        }
        return null;
    }

    public function findBySourceEntity(string $sourceEntityType, int $sourceEntityId): array
    {
        return array_values(array_filter($this->escalations, function (Escalation $e) use ($sourceEntityType, $sourceEntityId) {
            return $e->sourceEntityType() === $sourceEntityType && $e->sourceEntityId() === $sourceEntityId;
        }));
    }

    public function findOpen(): array
    {
        return array_values(array_filter($this->escalations, function (Escalation $e) {
            return in_array($e->status(), [Escalation::STATUS_OPEN, Escalation::STATUS_ACKED], true);
        }));
    }

    public function findOpenByDedupeKey(string $dedupeKey): ?Escalation
    {
        foreach ($this->escalations as $esc) {
            if ($esc->openDedupeKey() === $dedupeKey && in_array($esc->status(), [Escalation::STATUS_OPEN, Escalation::STATUS_ACKED], true)) {
                return $esc;
            }
        }
        return null;
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        return array_slice(array_values($this->escalations), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->escalations);
    }

    public function saveTransition(EscalationTransition $transition): void
    {
        $this->transitions[] = $transition;
    }

    public function findTransitionsByEscalationId(int $escalationId): array
    {
        return array_values(array_filter($this->transitions, function (EscalationTransition $t) use ($escalationId) {
            return $t->escalationId() === $escalationId;
        }));
    }

    /** @return Escalation[] */
    public function all(): array
    {
        return array_values($this->escalations);
    }

    /** @return EscalationTransition[] */
    public function allTransitions(): array
    {
        return $this->transitions;
    }
}
