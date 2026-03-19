<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Identity\Service;

use Pet\Application\Identity\Service\StaffEmployeeResolver;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use PHPUnit\Framework\TestCase;

final class StaffEmployeeResolverTest extends TestCase
{
    public function testResolveReturnsMissingWhenNoMappingExists(): void
    {
        $resolver = $this->makeResolver([]);
        $result = $resolver->resolve(7);

        self::assertFalse($result['ok']);
        self::assertSame('employee_mapping_missing', $result['code']);
        self::assertNull($result['employee']);
    }

    public function testResolveReturnsAmbiguousWhenMultipleMappingsExist(): void
    {
        $resolver = $this->makeResolver([
            $this->makeEmployee(11, 7),
            $this->makeEmployee(12, 7),
        ]);
        $result = $resolver->resolve(7);

        self::assertFalse($result['ok']);
        self::assertSame('employee_mapping_ambiguous', $result['code']);
        self::assertNull($result['employee']);
    }

    public function testResolveReturnsInactiveWhenMappedEmployeeIsInactive(): void
    {
        $resolver = $this->makeResolver([
            $this->makeEmployee(11, 7, 'inactive'),
        ]);
        $result = $resolver->resolve(7);

        self::assertFalse($result['ok']);
        self::assertSame('employee_inactive', $result['code']);
        self::assertNull($result['employee']);
    }

    public function testResolveReturnsInactiveWhenMappedEmployeeIsArchived(): void
    {
        $resolver = $this->makeResolver([
            $this->makeEmployee(11, 7, 'active', new \DateTimeImmutable('2026-03-01 10:00:00')),
        ]);
        $result = $resolver->resolve(7);

        self::assertFalse($result['ok']);
        self::assertSame('employee_inactive', $result['code']);
        self::assertNull($result['employee']);
    }

    public function testResolveReturnsEmployeeWhenSingleActiveMappingExists(): void
    {
        $employee = $this->makeEmployee(11, 7);
        $resolver = $this->makeResolver([$employee]);
        $result = $resolver->resolve(7);

        self::assertTrue($result['ok']);
        self::assertNull($result['code']);
        self::assertSame($employee, $result['employee']);
    }

    /**
     * @param Employee[] $employees
     */
    private function makeResolver(array $employees): StaffEmployeeResolver
    {
        $repository = $this->createMock(EmployeeRepository::class);
        $repository->method('findAll')->willReturn($employees);

        return new StaffEmployeeResolver($repository);
    }

    private function makeEmployee(int $id, int $wpUserId, string $status = 'active', ?\DateTimeImmutable $archivedAt = null): Employee
    {
        return new Employee(
            $wpUserId,
            'Sam',
            'Tech',
            'sam@example.com',
            $id,
            $status,
            null,
            null,
            null,
            null,
            [],
            [],
            new \DateTimeImmutable('2026-03-01 09:00:00'),
            $archivedAt
        );
    }
}
