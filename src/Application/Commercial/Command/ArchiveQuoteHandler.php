<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\QuoteRepository;

class ArchiveQuoteHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;

    public function __construct(TransactionManager $transactionManager, QuoteRepository $quoteRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(ArchiveQuoteCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $this->quoteRepository->delete($command->id());
    
        });
    }
}
