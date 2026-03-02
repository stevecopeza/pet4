<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use InvalidArgumentException;

class CreateEmployeeHandler
{
    private TransactionManager $transactionManager;
    private EmployeeRepository $employeeRepository;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(TransactionManager $transactionManager, 
        EmployeeRepository $employeeRepository,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->transactionManager = $transactionManager;
        $this->employeeRepository = $employeeRepository;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function handle(CreateEmployeeCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $activeSchema = $this->schemaRepository->findActiveByEntityType('employee');
        $malleableData = $command->malleableData();
        $schemaVersion = null;

        if ($activeSchema) {
            $schemaVersion = $activeSchema->version();
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            
            if (!empty($errors)) {
                throw new InvalidArgumentException('Invalid malleable data: ' . implode(', ', $errors));
            }
        }

        $employee = new Employee(
            $command->wpUserId(),
            $command->firstName(),
            $command->lastName(),
            $command->email(),
            null,
            $command->status(),
            $command->hireDate(),
            $command->managerId(),
            null,
            $schemaVersion,
            $malleableData,
            $command->teamIds()
        );

        $this->employeeRepository->save($employee);
    
        });
    }
}
