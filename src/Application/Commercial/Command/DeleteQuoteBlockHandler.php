<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;

final class DeleteQuoteBlockHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private QuoteBlockRepository $quoteBlockRepository;

    public function __construct(TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        QuoteBlockRepository $quoteBlockRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->quoteBlockRepository = $quoteBlockRepository;
    }

    public function handle(DeleteQuoteBlockCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
        }

        $blocks = $this->quoteBlockRepository->findByQuoteId($command->quoteId());

        $exists = false;

        foreach ($blocks as $block) {
            if ($block->id() === $command->blockId()) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            throw new \DomainException('Block not found');
        }

        $this->quoteBlockRepository->delete($command->blockId());
    
        });
    }
}

