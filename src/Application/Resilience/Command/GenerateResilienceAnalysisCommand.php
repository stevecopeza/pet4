<?php

declare(strict_types=1);

namespace Pet\Application\Resilience\Command;

final class GenerateResilienceAnalysisCommand
{
    public function __construct(
        private int $teamId,
        private ?int $generatedByWpUserId
    ) {
    }

    public function teamId(): int { return $this->teamId; }
    public function generatedByWpUserId(): ?int { return $this->generatedByWpUserId; }
}

