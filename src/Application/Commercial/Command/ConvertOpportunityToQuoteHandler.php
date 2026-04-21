<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\OpportunityRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;

class ConvertOpportunityToQuoteHandler
{
    public function __construct(
        private TransactionManager $transactionManager,
        private OpportunityRepository $opportunityRepository,
        private QuoteRepository $quoteRepository
    ) {}

    /**
     * Create a draft Quote from an Opportunity and return the new quote ID.
     * Idempotent: returns the existing quote if already linked.
     */
    public function handle(ConvertOpportunityToQuoteCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command) {
            $opportunity = $this->opportunityRepository->findById($command->opportunityId());
            if (!$opportunity) {
                throw new \DomainException("Opportunity '{$command->opportunityId()}' not found.");
            }

            if (!$opportunity->isOpen()) {
                throw new \DomainException(
                    "Cannot convert opportunity '{$opportunity->id()}' to quote: it is already closed."
                );
            }

            // Idempotency: return existing quote if already linked
            if ($opportunity->quoteId()) {
                $existing = $this->quoteRepository->findById($opportunity->quoteId());
                if ($existing) {
                    return $existing->id();
                }
            }

            $quote = new Quote(
                $opportunity->customerId(),
                $opportunity->name(),
                null, // description
                QuoteState::draft(),
                1,    // version
                $opportunity->estimatedValue(),
                0.00,
                $opportunity->currency() ?? 'ZAR',
                null, // acceptedAt
                null, // id (auto-assigned)
                new \DateTimeImmutable(),
                new \DateTimeImmutable(),
                null, // archivedAt
                [],   // components
                [],   // malleableData
                [],   // costAdjustments
                [],   // paymentSchedule
                $opportunity->leadId(),       // leadId (propagate from opp if set)
                $opportunity->id(),           // opportunityId
                null  // contractId
            );

            $this->quoteRepository->save($quote);

            // Link opportunity → quote
            $opportunity->linkQuote($quote->id());
            $this->opportunityRepository->save($opportunity);

            return $quote->id();
        });
    }
}
