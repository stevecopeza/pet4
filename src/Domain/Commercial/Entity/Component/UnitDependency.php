<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

final class UnitDependency
{
    private int $fromUnitId;
    private int $toUnitId;

    public function __construct(int $fromUnitId, int $toUnitId)
    {
        if ($fromUnitId === $toUnitId) {
            throw new \DomainException('Unit cannot depend on itself.');
        }
        $this->fromUnitId = $fromUnitId;
        $this->toUnitId = $toUnitId;
    }

    public function fromUnitId(): int
    {
        return $this->fromUnitId;
    }

    public function toUnitId(): int
    {
        return $this->toUnitId;
    }
}

