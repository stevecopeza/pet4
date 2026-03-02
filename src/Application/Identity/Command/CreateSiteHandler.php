<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Entity\Site;
use Pet\Domain\Identity\Repository\SiteRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;

class CreateSiteHandler
{
    private TransactionManager $transactionManager;
    private SiteRepository $siteRepository;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(TransactionManager $transactionManager, 
        SiteRepository $siteRepository,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->transactionManager = $transactionManager;
        $this->siteRepository = $siteRepository;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function handle(CreateSiteCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        // Handle Malleable Data
        $activeSchema = $this->schemaRepository->findActiveByEntityType('site');
        $malleableSchemaId = null;
        $malleableData = $command->malleableData();

        if ($activeSchema) {
            $malleableSchemaId = $activeSchema->id();
            // Validate data against schema
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            if (!empty($errors)) {
                throw new \InvalidArgumentException("Invalid site data: " . implode(', ', $errors));
            }
        }

        $site = new Site(
            $command->customerId(),
            $command->name(),
            $command->addressLines(),
            $command->city(),
            $command->state(),
            $command->postalCode(),
            $command->country(),
            $command->status(),
            null,
            $malleableSchemaId,
            $malleableData
        );

        $this->siteRepository->save($site);
    
        });
    }
}
