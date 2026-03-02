<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;

final class UpdateQuoteSectionHandler
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

    public function handle(UpdateQuoteSectionCommand $command): QuoteSection
    {
        return $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
        }

        $sections = $this->quoteSectionRepository->findByQuoteId($command->quoteId());

        $existing = null;

        foreach ($sections as $section) {
            if ($section->id() === $command->sectionId()) {
                $existing = $section;
                break;
            }
        }

        if ($existing === null) {
            throw new \DomainException('Section not found');
        }

        $updated = new QuoteSection(
            $existing->quoteId(),
            $command->name(),
            $existing->orderIndex(),
            $command->showTotalValue(),
            $command->showItemCount(),
            $command->showTotalHours(),
            $existing->id()
        );

        return $this->quoteSectionRepository->save($updated);
    
        });
    }
}

