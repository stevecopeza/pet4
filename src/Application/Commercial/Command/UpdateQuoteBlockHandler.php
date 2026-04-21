<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Commercial\Service\QuoteBlockCostSnapshotEnricher;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;

final class UpdateQuoteBlockHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private QuoteBlockRepository $quoteBlockRepository;
    private QuoteBlockCostSnapshotEnricher $costSnapshotEnricher;

    public function __construct(TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        QuoteBlockRepository $quoteBlockRepository,
        QuoteBlockCostSnapshotEnricher $costSnapshotEnricher
    ) {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->quoteBlockRepository = $quoteBlockRepository;
        $this->costSnapshotEnricher = $costSnapshotEnricher;
    }

    public function handle(UpdateQuoteBlockCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
        }
        if ($quote->state()->toString() === QuoteState::ACCEPTED) {
            throw new \DomainException('Cannot update blocks on an accepted quote.');
        }

        $blocks = $this->quoteBlockRepository->findByQuoteId($command->quoteId());

        $target = null;

        foreach ($blocks as $block) {
            if ($block->id() === $command->blockId()) {
                $target = $block;
                break;
            }
        }

        if ($target === null) {
            throw new \DomainException('Block not found');
        }

        $payload = $this->costSnapshotEnricher->enrichPayload($target->type(), $command->payload());

        $sellValue = 0.0;
        $internalCost = 0.0;

        if (isset($payload['totalValue']) && is_numeric($payload['totalValue'])) {
            $sellValue = (float) $payload['totalValue'];
        } elseif (isset($payload['sellValue']) && is_numeric($payload['sellValue'])) {
            $sellValue = (float) $payload['sellValue'];
        }

        if (isset($payload['totalCost']) && is_numeric($payload['totalCost'])) {
            $internalCost = (float) $payload['totalCost'];
        }

        $updated = new QuoteBlock(
            $target->position(),
            $target->type(),
            $target->componentId(),
            $sellValue,
            $internalCost,
            $target->isPriced(),
            $target->sectionId(),
            $payload,
            $target->id()
        );

        $this->quoteBlockRepository->update($updated, $command->quoteId());
    
        });
    }
}

