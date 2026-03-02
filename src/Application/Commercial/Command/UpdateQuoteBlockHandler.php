<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;

final class UpdateQuoteBlockHandler
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

    public function handle(UpdateQuoteBlockCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
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

        $payload = $command->payload();

        $sellValue = 0.0;

        if (isset($payload['totalValue']) && is_numeric($payload['totalValue'])) {
            $sellValue = (float) $payload['totalValue'];
        } elseif (isset($payload['sellValue']) && is_numeric($payload['sellValue'])) {
            $sellValue = (float) $payload['sellValue'];
        }

        $updated = new QuoteBlock(
            $target->position(),
            $target->type(),
            $target->componentId(),
            $sellValue,
            $target->internalCost(),
            $target->isPriced(),
            $target->sectionId(),
            $payload,
            $target->id()
        );

        $this->quoteBlockRepository->update($updated, $command->quoteId());
    
        });
    }
}

