<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\Event\PaymentScheduleDefinedEvent;
use Pet\Domain\Event\EventBus;

class SetPaymentScheduleHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private EventBus $eventBus;

    public function __construct(TransactionManager $transactionManager, QuoteRepository $quoteRepository, EventBus $eventBus)
    {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(SetPaymentScheduleCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if (!$quote) {
            throw new \RuntimeException("Quote not found: " . $command->quoteId());
        }

        if ($quote->state()->isTerminal()) {
            throw new \DomainException('Cannot change payment schedule for a finalized quote.');
        }

        $milestones = [];
        foreach ($command->milestones() as $data) {
            $milestones[] = new PaymentMilestone(
                $data['title'],
                (float) $data['amount'],
                !empty($data['dueDate']) ? new \DateTimeImmutable($data['dueDate']) : null
            );
        }

        $quote->setPaymentSchedule($milestones);
        $this->quoteRepository->save($quote);

        $totalAmount = 0.0;
        $itemsPayload = [];

        foreach ($quote->paymentSchedule() as $milestone) {
            $totalAmount += $milestone->amount();
            $itemsPayload[] = [
                'id' => $milestone->id(),
                'title' => $milestone->title(),
                'amount' => $milestone->amount(),
                'dueDate' => $milestone->dueDate(),
            ];
        }

        $event = new PaymentScheduleDefinedEvent(
            (int) $command->quoteId(),
            $totalAmount,
            $itemsPayload
        );

        $this->eventBus->dispatch($event);
    
        });
    }
}
