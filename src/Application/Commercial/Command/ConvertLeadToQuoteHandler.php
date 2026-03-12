<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\LeadRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;

class ConvertLeadToQuoteHandler
{
    private TransactionManager $transactionManager;
    private LeadRepository $leadRepository;
    private QuoteRepository $quoteRepository;

    public function __construct(
        TransactionManager $transactionManager,
        LeadRepository $leadRepository,
        QuoteRepository $quoteRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->leadRepository = $leadRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Convert a lead to a draft quote, returning the new quote ID.
     */
    public function handle(ConvertLeadToQuoteCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command) {
            $lead = $this->leadRepository->findById($command->leadId());
            if (!$lead) {
                throw new \DomainException("Lead not found: {$command->leadId()}");
            }

            if (!in_array($lead->status(), ['new', 'qualified'], true)) {
                throw new \DomainException(
                    "Lead cannot be converted from status '{$lead->status()}'. Must be 'new' or 'qualified'."
                );
            }

            // Check if already converted (idempotency)
            $existing = $this->quoteRepository->findByLeadId($lead->id());
            if ($existing) {
                return $existing->id();
            }

            // Transition lead to converted
            $lead->update(
                $lead->subject(),
                $lead->description(),
                'converted',
                $lead->source(),
                $lead->estimatedValue(),
                $lead->malleableData()
            );
            $this->leadRepository->save($lead);

            // Guard: customerId is required for quote creation
            if ($lead->customerId() === null) {
                throw new \DomainException(
                    "Cannot convert lead #{$lead->id()} to quote: customerId is required. Assign a customer first."
                );
            }

            // Create the linked quote
            $title = $command->title() ?: $lead->subject();
            $description = $command->description() ?? $lead->description();

            $quote = new Quote(
                $lead->customerId(),
                $title,
                $description,
                QuoteState::draft(),
                1,
                0.00,
                0.00,
                $command->currency(),
                null,
                null,
                new \DateTimeImmutable(),
                new \DateTimeImmutable(),
                null,
                [],
                [],
                [],
                [],
                $lead->id()
            );

            $this->quoteRepository->save($quote);

            return $quote->id();
        });
    }
}
