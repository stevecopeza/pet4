<?php

declare(strict_types=1);

namespace Pet\Domain\Advisory\Repository;

use Pet\Domain\Advisory\Entity\AdvisorySignal;

interface AdvisorySignalRepository
{
    public function save(AdvisorySignal $signal): void;
    public function findByWorkItemId(string $workItemId): array;
    public function findByWorkItemIds(array $workItemIds): array;
    public function findActiveByWorkItemId(string $workItemId): array; // Assuming signals can be resolved?
    
    /**
     * @return AdvisorySignal[]
     */
    public function findRecent(int $limit): array;

    public function clearForWorkItem(string $workItemId, ?string $generationRunId = null): void; // Deactivate old signals before regenerating
}
