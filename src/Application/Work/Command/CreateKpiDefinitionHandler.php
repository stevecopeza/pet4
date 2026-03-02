<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\KpiDefinition;
use Pet\Domain\Work\Repository\KpiDefinitionRepository;

class CreateKpiDefinitionHandler
{
    private TransactionManager $transactionManager;
    private KpiDefinitionRepository $kpiDefinitionRepository;

    public function __construct(TransactionManager $transactionManager, KpiDefinitionRepository $kpiDefinitionRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->kpiDefinitionRepository = $kpiDefinitionRepository;
    }

    public function handle(CreateKpiDefinitionCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $definition = new KpiDefinition(
            $command->name(),
            $command->description(),
            $command->defaultFrequency(),
            $command->unit()
        );

        $this->kpiDefinitionRepository->save($definition);
    
        });
    }
}
