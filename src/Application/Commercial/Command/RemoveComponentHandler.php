<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\QuoteRepository;

class RemoveComponentHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;

    public function __construct(TransactionManager $transactionManager, QuoteRepository $quoteRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(RemoveComponentCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new \DomainException("Quote not found: {$command->quoteId()}");
        }

        $quote->removeComponent($command->componentId());
        $this->quoteRepository->save($quote);
    
        });
    }
}
