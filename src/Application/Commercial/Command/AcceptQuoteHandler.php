<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Event\PaymentScheduleItemBecameDueEvent;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
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
    private CreateProjectTicketHandler $createProjectTicketHandler;
    private ?TouchedTracker $touched;
    private ?ProjectRepository $projectRepository;
    private ?ActionGatingService $gatingService;
    private ?AdminAuditLogger $auditLogger;

    public function __construct(TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        EventBus $eventBus,
        CreateProjectTicketHandler $createProjectTicketHandler,
        ?TouchedTracker $touched = null,
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
        $persistedQuote = $this->quoteRepository->findById($command->id(), true);
        if (!$persistedQuote) {
            throw new \RuntimeException('Quote not found after acceptance save');
        }
        $quote = $persistedQuote;
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

        $customerId = $quote->customerId();
        if ($customerId <= 0) {
            throw new \DomainException('Quote acceptance failed: missing customer_id for delivery ticket provisioning.');
        }
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

        $requiresProject = false;
        foreach ($quote->components() as $component) {
            if ($component instanceof ImplementationComponent
                || $component instanceof OnceOffServiceComponent
                || $component instanceof CatalogComponent
            ) {
                $requiresProject = true;
                break;
            }
        }
        if ($requiresProject && ($projectId === null || $projectId <= 0)) {
            throw new \RuntimeException('Quote acceptance failed: delivery project is missing for ticket provisioning.');
        }

        foreach ($quote->components() as $component) {
            if ($component instanceof ImplementationComponent) {
                $this->provisionImplementationComponentTickets($quote, $component, (int)$projectId);
            } elseif ($component instanceof OnceOffServiceComponent) {
                $this->provisionOnceOffServiceComponentTickets($quote, $component, (int)$projectId);
            } elseif ($component instanceof CatalogComponent) {
                $this->provisionCatalogComponentTickets($quote, $component, (int)$projectId);
            }
        }
    }

    private function provisionImplementationComponentTickets(Quote $quote, ImplementationComponent $component, int $projectId): void
    {
        $componentId = (int)$component->id();
        if ($componentId <= 0) {
            throw new \RuntimeException('Quote acceptance failed: implementation component missing persistent id.');
        }

        $taskRows = [];
        foreach ($component->milestones() as $milestone) {
            foreach ($milestone->tasks() as $task) {
                $sourceTaskId = (int)$task->id();
                if ($sourceTaskId <= 0) {
                    throw new \RuntimeException('Quote acceptance failed: quote task missing persistent id.');
                }
                $soldMinutes = (int) round($task->durationHours() * 60);
                $taskRows[] = [
                    'source_id' => $sourceTaskId,
                    'subject' => $task->title(),
                    'description' => $task->description() ?? '',
                    'sold_minutes' => $soldMinutes,
                    'sold_value_cents' => (int) round($task->sellValue() * 100),
                    'required_role_id' => $task->roleId(),
                ];
            }
        }

        if (empty($taskRows)) {
            return;
        }

        if (count($taskRows) === 1) {
            $single = $taskRows[0];
            $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
                $quote->customerId(),
                $projectId,
                (int)$quote->id(),
                $single['subject'],
                $single['description'],
                $single['sold_minutes'],
                $single['sold_value_cents'],
                $single['sold_minutes'],
                null,
                $single['required_role_id'],
                null,
                null,
                'quote_component',
                $componentId,
                null,
                false
            ));
            return;
        }

        $rollupSoldMinutes = 0;
        $rollupSoldValue = 0;
        foreach ($taskRows as $row) {
            $rollupSoldMinutes += (int)$row['sold_minutes'];
            $rollupSoldValue += (int)$row['sold_value_cents'];
        }

        $rollupSubject = trim((string)$component->description()) !== ''
            ? (string)$component->description()
            : ('Implementation Component #' . $componentId);
        $rollupId = $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
            $quote->customerId(),
            $projectId,
            (int)$quote->id(),
            $rollupSubject,
            'Rollup ticket for implementation component provisioning.',
            $rollupSoldMinutes,
            $rollupSoldValue,
            $rollupSoldMinutes,
            null,
            null,
            null,
            null,
            'quote_component',
            $componentId,
            null,
            true
        ));
        if ($rollupId <= 0) {
            throw new \RuntimeException('Quote acceptance failed: unable to create implementation rollup ticket.');
        }

        foreach ($taskRows as $row) {
            $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
                $quote->customerId(),
                $projectId,
                (int)$quote->id(),
                $row['subject'],
                $row['description'],
                (int)$row['sold_minutes'],
                (int)$row['sold_value_cents'],
                (int)$row['sold_minutes'],
                null,
                $row['required_role_id'],
                null,
                null,
                'quote_component',
                (int)$row['source_id'],
                (int)$rollupId,
                false
            ));
        }
    }

    private function provisionOnceOffServiceComponentTickets(Quote $quote, OnceOffServiceComponent $component, int $projectId): void
    {
        $componentId = (int)$component->id();
        if ($componentId <= 0) {
            throw new \RuntimeException('Quote acceptance failed: once-off service component missing persistent id.');
        }

        $units = $component->units();
        if (empty($units)) {
            return;
        }

        if (count($units) === 1) {
            $unit = $units[0];
            $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
                $quote->customerId(),
                $projectId,
                (int)$quote->id(),
                $unit->title(),
                $unit->description() ?? '',
                0,
                (int) round($unit->sellValue() * 100),
                0,
                null,
                null,
                null,
                null,
                'quote_component',
                $componentId,
                null,
                false
            ));
            return;
        }

        $rollupSubject = trim((string)$component->description()) !== ''
            ? (string)$component->description()
            : ('Service Component #' . $componentId);
        $rollupSoldValue = 0;
        foreach ($units as $unit) {
            $rollupSoldValue += (int) round($unit->sellValue() * 100);
        }
        $rollupId = $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
            $quote->customerId(),
            $projectId,
            (int)$quote->id(),
            $rollupSubject,
            'Rollup ticket for once-off service component provisioning.',
            0,
            $rollupSoldValue,
            0,
            null,
            null,
            null,
            null,
            'quote_component',
            $componentId,
            null,
            true
        ));
        if ($rollupId <= 0) {
            throw new \RuntimeException('Quote acceptance failed: unable to create once-off rollup ticket.');
        }

        foreach ($units as $index => $unit) {
            $sourceUnitId = (int)$unit->id();
            if ($sourceUnitId <= 0) {
                throw new \RuntimeException('Quote acceptance failed: once-off service unit missing persistent id at index ' . $index . '.');
            }
            $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
                $quote->customerId(),
                $projectId,
                (int)$quote->id(),
                $unit->title(),
                $unit->description() ?? '',
                0,
                (int) round($unit->sellValue() * 100),
                0,
                null,
                null,
                null,
                null,
                'quote_component',
                $sourceUnitId,
                (int)$rollupId,
                false
            ));
        }
    }

    private function provisionCatalogComponentTickets(Quote $quote, CatalogComponent $component, int $projectId): void
    {
        $componentId = (int)$component->id();
        if ($componentId <= 0) {
            throw new \RuntimeException('Quote acceptance failed: catalog component missing persistent id.');
        }

        $items = $component->items();
        if (empty($items)) {
            return;
        }

        // Single item — one ticket, anchored to the component id for idempotency.
        if (count($items) === 1) {
            $item = $items[0];
            $itemId = (int)$item->id();
            if ($itemId <= 0) {
                throw new \RuntimeException('Quote acceptance failed: catalog item missing persistent id.');
            }
            $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
                $quote->customerId(),
                $projectId,
                (int)$quote->id(),
                $this->catalogItemSubject($item),
                $item->description(),
                0,
                (int) round($item->sellValue() * 100),
                0,
                null,
                $item->roleId(),
                null,
                null,
                'quote_component',
                $componentId,
                null,
                false
            ));
            return;
        }

        // Multiple items — rollup ticket + one child per item.
        $rollupSubject = trim((string)$component->description()) !== ''
            ? (string)$component->description()
            : ('Catalog Component #' . $componentId);

        $rollupSoldValue = 0;
        foreach ($items as $item) {
            $rollupSoldValue += (int) round($item->sellValue() * 100);
        }

        $rollupId = $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
            $quote->customerId(),
            $projectId,
            (int)$quote->id(),
            $rollupSubject,
            'Rollup ticket for catalog component provisioning.',
            0,
            $rollupSoldValue,
            0,
            null,
            null,
            null,
            null,
            'quote_component',
            $componentId,
            null,
            true
        ));
        if ($rollupId <= 0) {
            throw new \RuntimeException('Quote acceptance failed: unable to create catalog rollup ticket.');
        }

        foreach ($items as $index => $item) {
            $itemId = (int)$item->id();
            if ($itemId <= 0) {
                throw new \RuntimeException('Quote acceptance failed: catalog item missing persistent id at index ' . $index . '.');
            }
            $this->createProjectTicketHandler->handle(new CreateProjectTicketCommand(
                $quote->customerId(),
                $projectId,
                (int)$quote->id(),
                $this->catalogItemSubject($item),
                $item->description(),
                0,
                (int) round($item->sellValue() * 100),
                0,
                null,
                $item->roleId(),
                null,
                null,
                'quote_component',
                $itemId,
                (int)$rollupId,
                false
            ));
        }
    }

    /**
     * Build a human-readable ticket subject for a catalog item.
     * Products: "Fulfil: 3× SKU-001"
     * Services: "Deliver service: 2× CONSULTING-HR"
     */
    private function catalogItemSubject(QuoteCatalogItem $item): string
    {
        $qty    = $item->quantity();
        $label  = $item->sku() ?? $item->description();
        $qtyStr = (floor($qty) === $qty) ? (string)(int)$qty : (string)$qty;

        if ($item->type() === 'product') {
            return 'Fulfil: ' . $qtyStr . '× ' . $label;
        }

        return 'Deliver service: ' . $qtyStr . '× ' . $label;
    }
}
