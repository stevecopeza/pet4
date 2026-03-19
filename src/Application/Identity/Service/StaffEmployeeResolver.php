<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Service;

use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;

final class StaffEmployeeResolver
{
    public function __construct(private EmployeeRepository $employeeRepository)
    {
    }

    /**
     * @return array{ok: bool, code: string|null, message: string|null, employee: Employee|null}
     */
    public function resolve(int $wpUserId): array
    {
        $matches = array_values(array_filter(
            $this->employeeRepository->findAll(),
            fn(Employee $employee) => $employee->wpUserId() === $wpUserId
        ));

        if (count($matches) === 0) {
            return [
                'ok' => false,
                'code' => 'employee_mapping_missing',
                'message' => 'No PET employee mapping exists for this user.',
                'employee' => null,
            ];
        }

        if (count($matches) > 1) {
            return [
                'ok' => false,
                'code' => 'employee_mapping_ambiguous',
                'message' => 'Multiple PET employee mappings exist for this user.',
                'employee' => null,
            ];
        }

        $employee = $matches[0];
        if ($employee->status() !== 'active' || $employee->isArchived()) {
            return [
                'ok' => false,
                'code' => 'employee_inactive',
                'message' => 'Mapped employee is inactive or archived.',
                'employee' => null,
            ];
        }

        return [
            'ok' => true,
            'code' => null,
            'message' => null,
            'employee' => $employee,
        ];
    }
}
