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
use Pet\Application\Commercial\Command\CreateProjectTicketHandler;
use Pet\Application\Commercial\Command\CreateProjectTicketCommand;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Application\Conversation\Service\ActionGatingService;
use Pet\Application\System\Service\AdminAuditLogger;

class AcceptQuoteHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private EventBus $eventBus;
    private ?TouchedTracker $touched;
    private ?CreateProjectTicketHandler $createProjectTicketHandler;
    private ?TicketRepository $ticketRepository;
    private ?ProjectRepository $projectRepository;
    private ?ActionGatingService $gatingService;
    private ?AdminAuditLogger $auditLogger;

    public function __construct(TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        EventBus $eventBus,
        ?TouchedTracker $touched = null,
        ?CreateProjectTicketHandler $createProjectTicketHandler = null,
        ?TicketRepository $ticketRepository = null,
        ?ProjectRepository $projectRepository = null,
        ?ActionGatingService $gatingService = null,
        ?AdminAuditLogger $auditLogger = null
    ) {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->eventBus = $eventBus;
        $this->touched = $touched;
        $this->createProjectTicketHandler = $createProjectTicketHandler;
        $this->ticketRepository = $ticketRepository;
        $this->projectRepository = $projectRepository;
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
        if ($this->createProjectTicketHandler === null) {
            return;
        }

        // Idempotency guard: if tickets already exist for this quote, skip creation
        if ($this->ticketRepository !== null) {
            $existingTickets = $this->ticketRepository->findByQuoteId((int) $quote->id());
            if (!empty($existingTickets)) {
                return;
            }
        }

        $customerId = $quote->customerId();
        $quoteId = (int) $quote->id();

        // Resolve projectId — CreateProjectFromQuoteListener runs synchronously
        // before this method, so the project should exist by now.
        $projectId = null;
        if ($this->projectRepository !== null) {
            $project = $this->projectRepository->findByQuoteId($quoteId);
            if ($project) {
                $projectId = $project->id();
            }
        }

        foreach ($quote->components() as $component) {
            if ($component instanceof ImplementationComponent) {
                foreach ($component->milestones() as $milestone) {
                    foreach ($milestone->tasks() as $task) {
                        $soldMinutes = (int) ($task->durationHours() * 60);
                        $soldValueCents = (int) round($task->sellValue() * 100);

                        $command = new CreateProjectTicketCommand(
                            $customerId,
                            $projectId,
                            $quoteId,
                            $task->title(),
                            $task->description() ?? '',
                            $soldMinutes,
                            $soldValueCents,
                            $soldMinutes,   // estimatedMinutes = soldMinutes initially
                            null,           // phaseId
                            $task->roleId(),
                            null            // departmentIdExt
                        );

                        $this->createProjectTicketHandler->handle($command);
                    }
                }
            } elseif ($component instanceof OnceOffServiceComponent) {
                foreach ($component->units() as $unit) {
                    $soldValueCents = (int) round($unit->sellValue() * 100);

                    $command = new CreateProjectTicketCommand(
                        $customerId,
                        $projectId,
                        $quoteId,
                        $unit->title(),
                        $unit->description() ?? '',
                        0,              // soldMinutes — catalog units have no duration
                        $soldValueCents,
                        0               // estimatedMinutes
                    );

                    $this->createProjectTicketHandler->handle($command);
                }
            }
        }
    }
}
