<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Repository\EmployeeRepository;

class ArchiveEmployeeHandler
{
    private TransactionManager $transactionManager;
    private EmployeeRepository $employeeRepository;

    public function __construct(TransactionManager $transactionManager, EmployeeRepository $employeeRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->employeeRepository = $employeeRepository;
    }

    public function handle(ArchiveEmployeeCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $employee = $this->employeeRepository->findById($command->id());

        if (!$employee) {
            throw new \RuntimeException('Employee not found');
        }

        // Archive logic: set archivedAt via the domain method.
        $employee->archive();
        $this->employeeRepository->save($employee);
    
        });
    }
}
