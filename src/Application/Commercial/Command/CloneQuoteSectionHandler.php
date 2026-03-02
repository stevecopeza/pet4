<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;

final class CloneQuoteSectionHandler
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

    public function handle(CloneQuoteSectionCommand $command): QuoteSection
    {
        return $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
        }

        $sections = $this->quoteSectionRepository->findByQuoteId($command->quoteId());

        $sourceSection = null;
        $maxOrderIndex = 0;

        foreach ($sections as $section) {
            if ($section->orderIndex() > $maxOrderIndex) {
                $maxOrderIndex = $section->orderIndex();
            }

            if ($section->id() === $command->sectionId()) {
                $sourceSection = $section;
            }
        }

        if ($sourceSection === null) {
            throw new \DomainException('Section not found');
        }

        $newSection = new QuoteSection(
            $sourceSection->quoteId(),
            $sourceSection->name(),
            $maxOrderIndex + 1000,
            $sourceSection->showTotalValue(),
            $sourceSection->showItemCount(),
            $sourceSection->showTotalHours()
        );

        $newSection = $this->quoteSectionRepository->save($newSection);

        $blocks = $this->quoteBlockRepository->findByQuoteId($command->quoteId());

        $maxPosition = 0;
        foreach ($blocks as $block) {
            if ($block->sectionId() === $newSection->id()) {
                if ($block->position() > $maxPosition) {
                    $maxPosition = $block->position();
                }
            }
        }

        $position = $maxPosition;

        foreach ($blocks as $block) {
            if ($block->sectionId() !== $sourceSection->id()) {
                continue;
            }

            $position += 1000;

            $clonedBlock = new QuoteBlock(
                $position,
                $block->type(),
                $block->componentId(),
                $block->sellValue(),
                $block->internalCost(),
                $block->isPriced(),
                $newSection->id(),
                $block->payload()
            );

            $this->quoteBlockRepository->insert($clonedBlock, $command->quoteId());
        }

        return $newSection;
    
        });
    }
}

