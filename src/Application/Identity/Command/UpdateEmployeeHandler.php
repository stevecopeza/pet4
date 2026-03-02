<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Identity\Entity\Employee;

use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use InvalidArgumentException;

class UpdateEmployeeHandler
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

    public function handle(UpdateEmployeeCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $employee = $this->employeeRepository->findById($command->id());

        if (!$employee) {
            throw new \RuntimeException('Employee not found');
        }

        // Validate Malleable Data against Active Schema (or existing version?)
        // For now, we validate against the currently active schema if one exists
        $activeSchema = $this->schemaRepository->findActiveByEntityType('employee');
        $malleableData = $command->malleableData();
        $schemaVersion = $employee->malleableSchemaVersion();

        if ($activeSchema) {
            // If there's an active schema, we might want to update the version to match
            // or we might want to validate against the employee's existing version.
            // Simplified logic: Use active schema for validation and update version.
            $schemaVersion = $activeSchema->version();
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            
            if (!empty($errors)) {
                throw new InvalidArgumentException('Invalid malleable data: ' . implode(', ', $errors));
            }
        }

        $updatedEmployee = new Employee(
            $command->wpUserId(),
            $command->firstName(),
            $command->lastName(),
            $command->email(),
            $employee->id(),
            $command->status(),
            $command->hireDate(),
            $command->managerId(),
            $employee->calendarId(),
            $schemaVersion,
            $malleableData,
            $command->teamIds(),
            $employee->createdAt(),
            $employee->archivedAt()
        );

        $this->employeeRepository->save($updatedEmployee);
    
        });
    }
}
