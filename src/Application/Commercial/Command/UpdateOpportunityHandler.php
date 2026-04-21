<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\OpportunityRepository;
use Pet\Application\System\Service\TransactionManager;

class UpdateOpportunityHandler
{
    public function __construct(
        private TransactionManager $transactionManager,
        private OpportunityRepository $opportunityRepository
    ) {}

    public function handle(UpdateOpportunityCommand $command): void
    {
        $opportunity = $this->opportunityRepository->findById($command->id());
        if (!$opportunity) {
            throw new \DomainException("Opportunity '{$command->id()}' not found.");
        }

        $closeDate = null;
        if ($command->expectedCloseDate()) {
            $closeDate = new \DateTimeImmutable($command->expectedCloseDate() . 'T00:00:00');
        }

        $opportunity->update(
            $command->name(),
            $command->stage(),
            $command->estimatedValue(),
            $command->currency(),
            $closeDate,
            $command->ownerId(),
            $command->qualification(),
            $command->notes()
        );

        $this->transactionManager->transactional(function () use ($opportunity) {
            $this->opportunityRepository->save($opportunity);
        });
    }
}
