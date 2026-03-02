<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\PersonKpi;
use Pet\Domain\Work\Repository\PersonKpiRepository;
use Pet\Domain\Work\Repository\RoleKpiRepository;

class GeneratePersonKpisHandler
{
    private TransactionManager $transactionManager;
    private PersonKpiRepository $personKpiRepository;
    private RoleKpiRepository $roleKpiRepository;

    public function __construct(TransactionManager $transactionManager, 
        PersonKpiRepository $personKpiRepository,
        RoleKpiRepository $roleKpiRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->personKpiRepository = $personKpiRepository;
        $this->roleKpiRepository = $roleKpiRepository;
    }

    public function handle(GeneratePersonKpisCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        // 1. Get Role KPIs
        $roleKpis = $this->roleKpiRepository->findByRoleId($command->roleId());

        // 2. Create Person KPI for each Role KPI
        foreach ($roleKpis as $roleKpi) {
            $personKpi = new PersonKpi(
                $command->employeeId(),
                $roleKpi->kpiDefinitionId(),
                $command->roleId(),
                $command->periodStart(),
                $command->periodEnd(),
                $roleKpi->targetValue()
            );

            $this->personKpiRepository->save($personKpi);
        }
    
        });
    }
}
