<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\Lead;
use Pet\Domain\Commercial\Repository\LeadRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use InvalidArgumentException;

class CreateLeadHandler
{
    private TransactionManager $transactionManager;
    private LeadRepository $leadRepository;
    private CustomerRepository $customerRepository;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(TransactionManager $transactionManager, 
        LeadRepository $leadRepository,
        CustomerRepository $customerRepository,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->transactionManager = $transactionManager;
        $this->leadRepository = $leadRepository;
        $this->customerRepository = $customerRepository;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function handle(CreateLeadCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $customer = $this->customerRepository->findById($command->customerId());
        if (!$customer) {
            throw new \DomainException("Customer not found: {$command->customerId()}");
        }

        $activeSchema = $this->schemaRepository->findActiveByEntityType('lead');
        $malleableData = $command->malleableData();
        $schemaVersion = null;

        if ($activeSchema) {
            $schemaVersion = $activeSchema->version();
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            
            if (!empty($errors)) {
                throw new InvalidArgumentException('Invalid malleable data: ' . implode(', ', $errors));
            }
        }

        $lead = new Lead(
            $command->customerId(),
            $command->subject(),
            $command->description(),
            'new',
            $command->source(),
            $command->estimatedValue(),
            null,
            $schemaVersion,
            $malleableData
        );

        $this->leadRepository->save($lead);
    
        });
    }
}
