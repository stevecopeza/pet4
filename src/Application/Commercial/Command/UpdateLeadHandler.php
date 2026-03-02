<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\LeadRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use InvalidArgumentException;

class UpdateLeadHandler
{
    private TransactionManager $transactionManager;
    private LeadRepository $leadRepository;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(TransactionManager $transactionManager, 
        LeadRepository $leadRepository,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->transactionManager = $transactionManager;
        $this->leadRepository = $leadRepository;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function handle(UpdateLeadCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $lead = $this->leadRepository->findById($command->id());
        if (!$lead) {
            throw new \DomainException("Lead not found: {$command->id()}");
        }

        $malleableData = $command->malleableData();
        
        // If lead has a schema version, validate against that version
        if ($lead->malleableSchemaVersion()) {
            $schema = $this->schemaRepository->findByEntityTypeAndVersion('lead', $lead->malleableSchemaVersion());
            if ($schema) {
                $errors = $this->schemaValidator->validateData($malleableData, $schema->schema());
                if (!empty($errors)) {
                    throw new InvalidArgumentException('Invalid malleable data: ' . implode(', ', $errors));
                }
            }
        } elseif (!empty($malleableData)) {
            // If no schema version but trying to save data, check if there's an active schema
            // If there is, we might want to adopt it, or reject if we strictly require schema assignment at creation
            // For now, let's allow updating if it matches active schema, or just skip validation if no schema assigned
            $activeSchema = $this->schemaRepository->findActiveByEntityType('lead');
            if ($activeSchema) {
                $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
                if (!empty($errors)) {
                    throw new InvalidArgumentException('Invalid malleable data: ' . implode(', ', $errors));
                }
            }
        }

        $lead->update(
            $command->subject(),
            $command->description(),
            $command->status(),
            $command->source(),
            $command->estimatedValue(),
            $malleableData
        );

        $this->leadRepository->save($lead);
    
        });
    }
}
