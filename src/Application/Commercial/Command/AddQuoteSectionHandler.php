<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;

class AddQuoteSectionHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private QuoteSectionRepository $quoteSectionRepository;

    public function __construct(TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        QuoteSectionRepository $quoteSectionRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->quoteSectionRepository = $quoteSectionRepository;
    }

    public function handle(AddQuoteSectionCommand $command): QuoteSection
    {
        return $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new \DomainException("Quote not found: {$command->quoteId()}");
        }

        $existing = $this->quoteSectionRepository->findByQuoteId($command->quoteId());
        $maxOrder = 0;
        foreach ($existing as $section) {
            if ($section->orderIndex() > $maxOrder) {
                $maxOrder = $section->orderIndex();
            }
        }

        $orderIndex = $maxOrder + 1000;
        if ($orderIndex === 1000 && empty($existing)) {
            $orderIndex = 1000;
        }

        $section = new QuoteSection(
            $command->quoteId(),
            $command->name(),
            $orderIndex,
            true,
            false,
            false
        );

        return $this->quoteSectionRepository->save($section);
    
        });
    }
}

