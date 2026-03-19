<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Event\TicketCreated;
use InvalidArgumentException;

class CreateTicketHandler
{
    private TransactionManager $transactionManager;
    private TicketRepository $ticketRepository;
    private CustomerRepository $customerRepository;
    private EventBus $eventBus;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(TransactionManager $transactionManager, 
        TicketRepository $ticketRepository,
        CustomerRepository $customerRepository,
        EventBus $eventBus,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->transactionManager = $transactionManager;
        $this->ticketRepository = $ticketRepository;
        $this->customerRepository = $customerRepository;
        $this->eventBus = $eventBus;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function handle(CreateTicketCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $customer = $this->customerRepository->findById($command->customerId());
        if (!$customer) {
            throw new \DomainException("Customer not found: {$command->customerId()}");
        }

        $activeSchema = $this->schemaRepository->findActiveByEntityType('ticket');
        $malleableData = $command->malleableData();
        $schemaVersion = null;

        if ($activeSchema) {
            $schemaVersion = $activeSchema->version();
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            
            if (!empty($errors)) {
                throw new InvalidArgumentException('Invalid malleable data: ' . implode(', ', $errors));
            }
        }

        $ticketMode = $malleableData['ticket_mode'] ?? 'support';

        $queueId = $malleableData['queue_id'] ?? null;
        $ownerUserId = $malleableData['owner_user_id'] ?? null;
        $category = $malleableData['category'] ?? null;
        $subcategory = $malleableData['subcategory'] ?? null;
        $intakeSource = $malleableData['intake_source'] ?? ($malleableData['source'] ?? null);
        $contactId = isset($malleableData['contact_id']) ? (int)$malleableData['contact_id'] : null;

        $hasQueue = $queueId !== null && $queueId !== '';
        $hasOwner = $ownerUserId !== null && $ownerUserId !== '';
        if (!($hasQueue xor $hasOwner)) {
            throw new \DomainException('Support tickets require exactly one operational owner (team or user).');
        }

        $ticket = new Ticket(
            $command->customerId(),
            $command->subject(),
            $command->description(),
            'new',
            $command->priority(),
            $command->siteId(),
            $command->slaId(),
            null,
            $schemaVersion,
            $malleableData,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $queueId !== null ? (string) $queueId : null,
            $ownerUserId !== null ? (string) $ownerUserId : null,
            $category,
            $subcategory,
            $intakeSource,
            $contactId
        );

        $this->ticketRepository->save($ticket);

        // Dispatch event
        $this->eventBus->dispatch(new TicketCreated($ticket));
    
        });
    }
}
