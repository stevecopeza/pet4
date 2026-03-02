<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use Pet\Domain\Delivery\Event\ProjectCreated;
use Pet\Domain\Event\EventBus;

class CreateProjectHandler
{
    private TransactionManager $transactionManager;
    private ProjectRepository $projectRepository;
    private CustomerRepository $customerRepository;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;
    private EventBus $eventBus;

    public function __construct(TransactionManager $transactionManager, 
        ProjectRepository $projectRepository,
        CustomerRepository $customerRepository,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator,
        EventBus $eventBus
    ) {
        $this->transactionManager = $transactionManager;
        $this->projectRepository = $projectRepository;
        $this->customerRepository = $customerRepository;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
        $this->eventBus = $eventBus;
    }

    public function handle(CreateProjectCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $customer = $this->customerRepository->findById($command->customerId());
        if (!$customer) {
            throw new \DomainException("Customer not found: {$command->customerId()}");
        }

        // Handle Malleable Data
        $activeSchema = $this->schemaRepository->findActiveByEntityType('project');
        $malleableSchemaId = null;
        $malleableData = $command->malleableData();

        if ($activeSchema) {
            $malleableSchemaId = $activeSchema->id();
            // Validate data against schema
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            if (!empty($errors)) {
                throw new \InvalidArgumentException("Invalid project data: " . implode(', ', $errors));
            }
        }

        $project = new Project(
            $command->customerId(),
            $command->name(),
            $command->soldHours(),
            $command->sourceQuoteId(),
            null, // state
            $command->soldValue(),
            $command->startDate(),
            $command->endDate(),
            null, // id
            $malleableSchemaId,
            $malleableData,
            null, // createdAt
            null, // updatedAt
            null, // archivedAt
            $command->tasks()
        );

        $this->projectRepository->save($project);

        $this->eventBus->dispatch(new ProjectCreated($project));
    
        });
    }
}
