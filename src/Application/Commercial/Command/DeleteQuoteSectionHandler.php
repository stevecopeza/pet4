<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;

final class DeleteQuoteSectionHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private QuoteSectionRepository $quoteSectionRepository;
    private QuoteBlockRepository $quoteBlockRepository;

    public function __construct(TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        QuoteSectionRepository $quoteSectionRepository,
        QuoteBlockRepository $quoteBlockRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->quoteSectionRepository = $quoteSectionRepository;
        $this->quoteBlockRepository = $quoteBlockRepository;
    }

    public function handle(DeleteQuoteSectionCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
        }

        $blocks = $this->quoteBlockRepository->findByQuoteId($command->quoteId());

        $blocksInSection = array_filter(
            $blocks,
            function (QuoteBlock $block) use ($command): bool {
                return $block->sectionId() === $command->sectionId();
            }
        );

        if (!empty($blocksInSection)) {
            $hasNonTextBlocks = false;

            foreach ($blocksInSection as $block) {
                if ($block->type() !== QuoteBlock::TYPE_TEXT) {
                    $hasNonTextBlocks = true;
                    break;
                }
            }

            if ($hasNonTextBlocks) {
                throw new \DomainException('Cannot delete section that still contains blocks.');
            }

            foreach ($blocksInSection as $block) {
                if ($block->id() !== null) {
                    $this->quoteBlockRepository->delete($block->id());
                }
            }
        }

        $this->quoteSectionRepository->delete($command->sectionId());
    
        });
    }
}
