<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\QuoteRepository;

class UpdateQuoteHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;

    public function __construct(TransactionManager $transactionManager, QuoteRepository $quoteRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(UpdateQuoteCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->id());

        if (!$quote) {
            throw new \RuntimeException('Quote not found');
        }

        $quote->update(
            $command->customerId(),
            $command->currency(),
            $command->acceptedAt(),
            $command->malleableData()
        );

        $this->quoteRepository->save($quote);
    
        });
    }
}
