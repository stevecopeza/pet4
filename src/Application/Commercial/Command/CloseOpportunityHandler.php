<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\OpportunityRepository;
use Pet\Application\System\Service\TransactionManager;

class CloseOpportunityHandler
{
    public function __construct(
        private TransactionManager $transactionManager,
        private OpportunityRepository $opportunityRepository
    ) {}

    public function handle(CloseOpportunityCommand $command): void
    {
        $opportunity = $this->opportunityRepository->findById($command->id());
        if (!$opportunity) {
            throw new \DomainException("Opportunity '{$command->id()}' not found.");
        }

        $opportunity->close($command->stage());

        $this->transactionManager->transactional(function () use ($opportunity) {
            $this->opportunityRepository->save($opportunity);
        });
    }
}
