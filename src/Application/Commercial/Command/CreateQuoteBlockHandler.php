<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Commercial\Service\QuoteBlockCostSnapshotEnricher;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;

final class CreateQuoteBlockHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private QuoteSectionRepository $quoteSectionRepository;
    private QuoteBlockRepository $quoteBlockRepository;
    private QuoteBlockCostSnapshotEnricher $costSnapshotEnricher;

    public function __construct(TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        QuoteSectionRepository $quoteSectionRepository,
        QuoteBlockRepository $quoteBlockRepository,
        QuoteBlockCostSnapshotEnricher $costSnapshotEnricher
    ) {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->quoteSectionRepository = $quoteSectionRepository;
        $this->quoteBlockRepository = $quoteBlockRepository;
        $this->costSnapshotEnricher = $costSnapshotEnricher;
    }

    public function handle(CreateQuoteBlockCommand $command): QuoteBlock
    {
        return $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
        }
        if ($quote->state()->toString() === QuoteState::ACCEPTED) {
            throw new \DomainException('Cannot create blocks on an accepted quote.');
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

        $payload = $this->costSnapshotEnricher->enrichPayload($command->type(), $command->payload());

        $sellValue = 0.0;
        if (isset($payload['totalValue']) && is_numeric($payload['totalValue'])) {
            $sellValue = (float) $payload['totalValue'];
        } elseif (isset($payload['sellValue']) && is_numeric($payload['sellValue'])) {
            $sellValue = (float) $payload['sellValue'];
        }

        $internalCost = 0.0;
        if (isset($payload['totalCost']) && is_numeric($payload['totalCost'])) {
            $internalCost = (float) $payload['totalCost'];
        }

        $block = new QuoteBlock(
            $position,
            $command->type(),
            null,
            $sellValue,
            $internalCost,
            true,
            $sectionId,
            $payload
        );

        return $this->quoteBlockRepository->insert($block, $command->quoteId());
    
        });
    }
}

