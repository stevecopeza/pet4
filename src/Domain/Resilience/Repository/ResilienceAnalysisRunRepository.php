<?php

declare(strict_types=1);

namespace Pet\Domain\Resilience\Repository;

use Pet\Domain\Resilience\Entity\ResilienceAnalysisRun;

interface ResilienceAnalysisRunRepository
{
    public function save(ResilienceAnalysisRun $run): void;

    public function findById(string $id): ?ResilienceAnalysisRun;

    public function findLatestByScope(string $scopeType, int $scopeId): ?ResilienceAnalysisRun;

    /**
     * @return ResilienceAnalysisRun[]
     */
    public function findByScope(string $scopeType, int $scopeId, int $limit = 50): array;

    public function findNextVersionNumber(string $scopeType, int $scopeId): int;
}

