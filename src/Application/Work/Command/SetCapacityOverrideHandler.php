<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Repository\CapacityOverrideRepository;

final class SetCapacityOverrideHandler
{
    private TransactionManager $transactionManager;
    public function __construct(TransactionManager $transactionManager, private CapacityOverrideRepository $repo)
    {
        $this->transactionManager = $transactionManager;
    }

    public function handle(SetCapacityOverrideCommand $c): void
    {
        $this->transactionManager->transactional(function () use ($c) {
        $pct = max(0, min(100, $c->capacityPct()));
        $this->repo->setOverride($c->employeeId(), $c->date(), $pct, $c->reason());
    
        });
    }
}

