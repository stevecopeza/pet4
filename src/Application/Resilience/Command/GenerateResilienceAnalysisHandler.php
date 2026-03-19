<?php

declare(strict_types=1);

namespace Pet\Application\Resilience\Command;

use Pet\Application\Resilience\Service\ResilienceAnalysisGenerator;
use Pet\Application\System\Service\TransactionManager;

final class GenerateResilienceAnalysisHandler
{
    public function __construct(
        private TransactionManager $transactionManager,
        private ResilienceAnalysisGenerator $generator
    ) {
    }

    public function handle(GenerateResilienceAnalysisCommand $c): string
    {
        return $this->transactionManager->transactional(function () use ($c) {
            return $this->generator->generateForTeam($c->teamId(), $c->generatedByWpUserId());
        });
    }
}

