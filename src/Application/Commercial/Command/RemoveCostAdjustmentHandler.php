<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\CostAdjustmentRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use RuntimeException;

class RemoveCostAdjustmentHandler
{
    private TransactionManager $transactionManager;
    private CostAdjustmentRepository $costAdjustmentRepository;
    private QuoteRepository $quoteRepository;

    public function __construct(TransactionManager $transactionManager, 
        CostAdjustmentRepository $costAdjustmentRepository,
        QuoteRepository $quoteRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->costAdjustmentRepository = $costAdjustmentRepository;
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(RemoveCostAdjustmentCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new RuntimeException("Quote not found: " . $command->quoteId());
        }

        if ($quote->state()->isTerminal()) {
            throw new RuntimeException("Cannot remove cost adjustments from a finalized quote.");
        }

        // Verify the adjustment belongs to the quote
        $adjustments = $this->costAdjustmentRepository->findByQuoteId($command->quoteId());
        $found = false;
        foreach ($adjustments as $adjustment) {
            if ($adjustment->id() === $command->adjustmentId()) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new RuntimeException("Cost adjustment not found for this quote.");
        }

        $this->costAdjustmentRepository->delete($command->adjustmentId());
    
        });
    }
}
