<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Repository\QuoteRepository;

class AddQuoteLineHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;

    public function __construct(TransactionManager $transactionManager, QuoteRepository $quoteRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(AddQuoteLineCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new \DomainException("Quote not found: {$command->quoteId()}");
        }

        // Map legacy "Line" to a Catalog Component with a single item
        $item = new QuoteCatalogItem(
            $command->description(),
            $command->quantity(),
            $command->unitPrice(),
            0.00 // Default internal cost to 0 as it's not provided in legacy command
        );

        $component = new CatalogComponent(
            [$item],
            $command->description() // Use line description as component description
        );

        $quote->addComponent($component);

        $this->quoteRepository->save($quote);
    
        });
    }
}
