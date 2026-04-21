<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;

class SubmitQuoteForApprovalHandler
{
    public function __construct(private QuoteRepository $quoteRepository) {}

    public function handle(SubmitQuoteForApprovalCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->quoteId);

        if (!$quote) {
            throw new \DomainException("Quote #{$command->quoteId} not found.");
        }

        $quote->submitForApproval();
        $this->quoteRepository->save($quote);
    }
}
