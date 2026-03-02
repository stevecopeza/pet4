<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Repository\PersonKpiRepository;

class UpdatePersonKpiHandler
{
    private TransactionManager $transactionManager;
    private PersonKpiRepository $personKpiRepository;

    public function __construct(TransactionManager $transactionManager, PersonKpiRepository $personKpiRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->personKpiRepository = $personKpiRepository;
    }

    public function handle(UpdatePersonKpiCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $personKpi = $this->personKpiRepository->findById($command->id());

        if (!$personKpi) {
            throw new \RuntimeException('Person KPI not found');
        }

        // Domain logic: update actual and score
        // We might want to move calculation logic to the entity or domain service, 
        // but for now, we accept the score from the command (calculated by UI or service).
        $personKpi->updateActual($command->actualValue(), $command->score());

        $this->personKpiRepository->save($personKpi);
    
        });
    }
}
