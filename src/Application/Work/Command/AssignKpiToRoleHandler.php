<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\RoleKpi;
use Pet\Domain\Work\Repository\RoleKpiRepository;

class AssignKpiToRoleHandler
{
    private TransactionManager $transactionManager;
    private RoleKpiRepository $roleKpiRepository;

    public function __construct(TransactionManager $transactionManager, RoleKpiRepository $roleKpiRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->roleKpiRepository = $roleKpiRepository;
    }

    public function handle(AssignKpiToRoleCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $roleKpi = new RoleKpi(
            $command->roleId(),
            $command->kpiDefinitionId(),
            $command->weightPercentage(),
            $command->targetValue(),
            $command->measurementFrequency()
        );

        $this->roleKpiRepository->save($roleKpi);
    
        });
    }
}
