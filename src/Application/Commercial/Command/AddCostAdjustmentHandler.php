<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\CostAdjustment;
use Pet\Domain\Commercial\Repository\CostAdjustmentRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Commercial\Event\ChangeOrderApprovedEvent;
use RuntimeException;

class AddCostAdjustmentHandler
{
    private TransactionManager $transactionManager;
    private CostAdjustmentRepository $costAdjustmentRepository;
    private QuoteRepository $quoteRepository;
    private EventBus $eventBus;

    public function __construct(TransactionManager $transactionManager, 
        CostAdjustmentRepository $costAdjustmentRepository,
        QuoteRepository $quoteRepository,
        EventBus $eventBus
    ) {
        $this->transactionManager = $transactionManager;
        $this->costAdjustmentRepository = $costAdjustmentRepository;
        $this->quoteRepository = $quoteRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(AddCostAdjustmentCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new RuntimeException("Quote not found: " . $command->quoteId());
        }

        if ($quote->state()->isTerminal()) {
            throw new RuntimeException("Cannot add cost adjustments to a finalized quote.");
        }

        $adjustment = new CostAdjustment(
            $command->quoteId(),
            $command->description(),
            $command->amount(),
            $command->reason(),
            $command->approvedBy()
        );

        $this->costAdjustmentRepository->save($adjustment);
        
        $this->eventBus->dispatch(new ChangeOrderApprovedEvent($adjustment));
    
        });
    }
}
