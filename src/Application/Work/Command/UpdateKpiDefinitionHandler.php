<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\KpiDefinition;
use Pet\Domain\Work\Repository\KpiDefinitionRepository;

class UpdateKpiDefinitionHandler
{
    private TransactionManager $transactionManager;
    private KpiDefinitionRepository $kpiDefinitionRepository;

    public function __construct(TransactionManager $transactionManager, KpiDefinitionRepository $kpiDefinitionRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->kpiDefinitionRepository = $kpiDefinitionRepository;
    }

    public function handle(UpdateKpiDefinitionCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $existing = $this->kpiDefinitionRepository->findById($command->id());

        if (!$existing) {
            throw new \RuntimeException('KPI Definition not found');
        }

        $updated = new KpiDefinition(
            $command->name(),
            $command->description(),
            $command->defaultFrequency(),
            $command->unit(),
            $existing->id(),
            $existing->createdAt()
        );

        $this->kpiDefinitionRepository->save($updated);
    
        });
    }
}

