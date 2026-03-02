<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Repository;

use Pet\Domain\Sla\Entity\SlaDefinition;
use Pet\Domain\Sla\Entity\SlaSnapshot;

interface SlaRepository
{
    public function save(SlaDefinition $sla): void;
    public function findById(int $id): ?SlaDefinition;
    public function findByUuid(string $uuid): ?SlaDefinition;
    public function findAll(): array;
    public function delete(int $id): void;

    public function saveSnapshot(SlaSnapshot $snapshot): int;
    public function findSnapshotById(int $id): ?SlaSnapshot;
}
