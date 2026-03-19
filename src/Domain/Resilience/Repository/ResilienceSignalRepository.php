<?php

declare(strict_types=1);

namespace Pet\Domain\Resilience\Repository;

use Pet\Domain\Resilience\Entity\ResilienceSignal;

interface ResilienceSignalRepository
{
    public function save(ResilienceSignal $signal): void;

    /**
     * @return ResilienceSignal[]
     */
    public function findByAnalysisRunId(string $analysisRunId): array;

    /**
     * @return ResilienceSignal[]
     */
    public function findActiveByScope(string $scopeType, int $scopeId, int $limit = 200): array;

    public function deactivateActiveForScope(string $scopeType, int $scopeId, ?string $resolvedAtUtc = null): void;
}

