<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;

final class CreateQuoteBlockHandler
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

    public function handle(CreateQuoteBlockCommand $command): QuoteBlock
    {
        return $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
        }

        $sectionId = $command->sectionId();

        if ($sectionId !== null) {
            $sections = $this->quoteSectionRepository->findByQuoteId($command->quoteId());
            $sectionIds = array_map(static function ($section) {
                return $section->id();
            }, $sections);

            if (!in_array($sectionId, $sectionIds, true)) {
                throw new \DomainException('Section does not belong to quote');
            }
        }

        $existingBlocks = $this->quoteBlockRepository->findByQuoteId($command->quoteId());

        $maxPosition = 0;
        foreach ($existingBlocks as $block) {
            if ($block->sectionId() === $sectionId && $block->position() > $maxPosition) {
                $maxPosition = $block->position();
            }
        }

        $position = $maxPosition + 1000;

        $block = new QuoteBlock(
            $position,
            $command->type(),
            null,
            0.0,
            0.0,
            true,
            $sectionId,
            $command->payload()
        );

        return $this->quoteBlockRepository->insert($block, $command->quoteId());
    
        });
    }
}

