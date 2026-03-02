<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Identity\Entity\Employee;

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

        // Archive logic: Set archivedAt to now
        $archivedEmployee = new Employee(
            $employee->wpUserId(),
            $employee->firstName(),
            $employee->lastName(),
            $employee->email(),
            $employee->id(),
            $employee->malleableSchemaVersion(),
            $employee->malleableData(),
            $employee->createdAt(),
            new \DateTimeImmutable() // Set archivedAt
        );

        $this->employeeRepository->save($archivedEmployee);
    
        });
    }
}
