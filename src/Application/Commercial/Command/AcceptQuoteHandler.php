<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Event\PaymentScheduleItemBecameDueEvent;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Event\EventBus;
use Pet\Application\System\Service\TouchedTracker;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\CreateTicketCommand;
use Pet\Application\Conversation\Service\ActionGatingService;
use Pet\Application\System\Service\AdminAuditLogger;

class AcceptQuoteHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private EventBus $eventBus;
    private ?TouchedTracker $touched;
    private ?CreateTicketHandler $createTicketHandler;
    private ?ActionGatingService $gatingService;
    private ?AdminAuditLogger $auditLogger;

    public function __construct(TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        EventBus $eventBus,
        ?TouchedTracker $touched = null,
        ?CreateTicketHandler $createTicketHandler = null,
        ?ActionGatingService $gatingService = null,
        ?AdminAuditLogger $auditLogger = null
    ) {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->eventBus = $eventBus;
        $this->touched = $touched;
        $this->createTicketHandler = $createTicketHandler;
        $this->gatingService = $gatingService;
        $this->auditLogger = $auditLogger;
    }

    public function handle(AcceptQuoteCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->id(), true);

        if (!$quote) {
            throw new \RuntimeException('Quote not found');
        }

        if ($this->gatingService) {
            $this->gatingService->check('quote', (string)$quote->id(), 'accept_quote', $quote->version());
        }

        $quote->accept();
        $this->quoteRepository->save($quote);
        $this->eventBus->dispatch(new QuoteAccepted($quote));

        foreach ($quote->paymentSchedule() as $milestone) {
            if ($milestone->dueDate() === null) {
                if ($milestone->id() === null) {
                    continue;
                }

                $event = new PaymentScheduleItemBecameDueEvent(
                    (int) $quote->id(),
                    (int) $milestone->id(),
                    $milestone->title(),
                    $milestone->amount(),
                    $milestone->dueDate()
                );

                $this->eventBus->dispatch($event);
            }
        }

        if ($this->touched !== null) {
            $employeeId = 1;
            $this->touched->mark('quote', (int)$quote->id(), $employeeId);
        }

        $this->createTicketsFromQuote($quote);

        $this->auditLogger?->log('quote_accepted', [
            'quote_id' => $quote->id(),
            'customer_id' => $quote->customerId(),
        ]);
    
        });
    }

    private function createTicketsFromQuote(Quote $quote): void
    {
        if ($this->createTicketHandler === null) {
            return;
        }

        $customerId = $quote->customerId();

        foreach ($quote->components() as $component) {
            if ($component instanceof ImplementationComponent) {
                foreach ($component->milestones() as $milestone) {
                    foreach ($milestone->tasks() as $task) {
                        $description = $task->description() ?? '';

                        $malleableData = [
                            'source' => 'quote',
                            'quote_id' => $quote->id(),
                            'quote_component_id' => $component->id(),
                            'quote_milestone_id' => $milestone->id(),
                            'quote_task_row_id' => $task->id(),
                            'sold_hours' => $task->durationHours(),
                            'role_id' => $task->roleId(),
                            'ticket_mode' => 'execution',
                        ];

                        $command = new CreateTicketCommand(
                            $customerId,
                            null,
                            null,
                            $task->title(),
                            $description,
                            'medium',
                            $malleableData
                        );

                        $this->createTicketHandler->handle($command);
                    }
                }
            } elseif ($component instanceof OnceOffServiceComponent) {
                if ($component->topology() === OnceOffServiceComponent::TOPOLOGY_SIMPLE) {
                    foreach ($component->units() as $unit) {
                        $malleableData = [
                            'source' => 'quote',
                            'quote_id' => $quote->id(),
                            'quote_component_id' => $component->id(),
                            'quote_simple_unit_id' => $unit->id(),
                            'unit_quantity' => $unit->quantity(),
                            'unit_sell_price' => $unit->unitSellPrice(),
                            'unit_internal_cost' => $unit->unitInternalCost(),
                            'ticket_mode' => 'execution',
                        ];

                        $command = new CreateTicketCommand(
                            $customerId,
                            null,
                            null,
                            $unit->title(),
                            $unit->description() ?? '',
                            'medium',
                            $malleableData
                        );

                        $this->createTicketHandler->handle($command);
                    }
                } else {
                    foreach ($component->phases() as $phase) {
                        foreach ($phase->units() as $unit) {
                            $malleableData = [
                                'source' => 'quote',
                                'quote_id' => $quote->id(),
                                'quote_component_id' => $component->id(),
                                'quote_phase_id' => $phase->id(),
                                'quote_phase_name' => $phase->name(),
                                'quote_simple_unit_id' => $unit->id(),
                                'unit_quantity' => $unit->quantity(),
                                'unit_sell_price' => $unit->unitSellPrice(),
                                'unit_internal_cost' => $unit->unitInternalCost(),
                                'ticket_mode' => 'execution',
                            ];

                            $command = new CreateTicketCommand(
                                $customerId,
                                null,
                                null,
                                $unit->title(),
                                $unit->description() ?? '',
                                'medium',
                                $malleableData
                            );

                            $this->createTicketHandler->handle($command);
                        }
                    }
                }
            }
        }
    }
}
