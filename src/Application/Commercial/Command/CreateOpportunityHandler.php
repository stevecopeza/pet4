<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Entity\Opportunity;
use Pet\Domain\Commercial\Repository\OpportunityRepository;
use Pet\Application\System\Service\TransactionManager;

class CreateOpportunityHandler
{
    public function __construct(
        private TransactionManager $transactionManager,
        private OpportunityRepository $opportunityRepository
    ) {}

    public function handle(CreateOpportunityCommand $command): Opportunity
    {
        $closeDate = null;
        if ($command->expectedCloseDate()) {
            $closeDate = new \DateTimeImmutable($command->expectedCloseDate() . 'T00:00:00');
        }

        $opportunity = new Opportunity(
            wp_generate_uuid4(),
            $command->customerId(),
            $command->name(),
            $command->stage() ?: Opportunity::STAGE_DISCOVERY,
            $command->estimatedValue(),
            $command->ownerId(),
            $command->leadId(),
            $command->currency() ?: 'ZAR',
            $closeDate,
            $command->qualification(),
            $command->notes()
        );

        $this->transactionManager->transactional(function () use ($opportunity) {
            $this->opportunityRepository->save($opportunity);
        });

        return $opportunity;
    }
}
