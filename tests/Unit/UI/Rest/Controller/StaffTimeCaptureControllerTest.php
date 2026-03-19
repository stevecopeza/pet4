<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

use Pet\Application\Identity\Service\StaffEmployeeResolver;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Application\Time\Command\LogTimeCommand;
use Pet\Application\Time\Command\LogTimeHandler;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\UI\Rest\Controller\StaffTimeCaptureController;
use PHPUnit\Framework\TestCase;

final class StaffTimeCaptureControllerTest extends TestCase
{
    public function testCheckPermissionReturnsFalseWhenFeatureFlagDisabled(): void
    {
        $controller = $this->makeController([
            'flag' => false,
        ]);

        self::assertFalse($controller->checkPermission());
    }

    public function testCreateEntryUsesResolvedEmployeeIdInsteadOfClientPayloadEmployeeId(): void
    {
        $resolvedEmployee = $this->makeEmployee(11, 1);
        $captured = null;

        $controller = $this->makeController([
            'flag' => true,
            'employees' => [$resolvedEmployee],
            'log_handler' => function (LogTimeCommand $command) use (&$captured): int {
                $captured = $command;
                return 321;
            },
        ]);

        $request = new \WP_REST_Request('POST', '/pet/v1/staff/time-capture/entries');
        $request->set_json_params([
            'employeeId' => 999,
            'ticketId' => 88,
            'start' => '2026-03-18 09:00:00',
            'end' => '2026-03-18 09:30:00',
            'isBillable' => true,
            'description' => 'Investigated outage',
        ]);

        $response = $controller->createEntry($request);
        $data = $response->get_data();

        self::assertSame(201, $response->get_status());
        self::assertSame(['message' => 'Time logged', 'id' => 321], $data);
        self::assertInstanceOf(LogTimeCommand::class, $captured);
        self::assertSame(11, $captured->employeeId());
        self::assertSame(88, $captured->ticketId());
    }

    public function testGetEntriesReturns403AndIdentityCodeWhenMappingIsAmbiguous(): void
    {
        $controller = $this->makeController([
            'flag' => true,
            'employees' => [
                $this->makeEmployee(11, 1),
                $this->makeEmployee(12, 1),
            ],
        ]);

        $request = new \WP_REST_Request('GET', '/pet/v1/staff/time-capture/entries');
        $response = $controller->getEntries($request);
        $data = $response->get_data();

        self::assertSame(403, $response->get_status());
        self::assertSame('employee_mapping_ambiguous', $data['code'] ?? null);
    }

    /**
     * @param array{
     *     flag?: bool,
     *     employees?: Employee[],
     *     log_handler?: callable(LogTimeCommand): int
     * } $options
     */
    private function makeController(array $options = []): StaffTimeCaptureController
    {
        $flags = $this->createMock(FeatureFlagService::class);
        $flags->method('isStaffTimeCaptureEnabled')->willReturn($options['flag'] ?? true);

        $employeeRepository = $this->createMock(EmployeeRepository::class);
        $employeeRepository->method('findAll')->willReturn($options['employees'] ?? [$this->makeEmployee(11, 1)]);
        $resolver = new StaffEmployeeResolver($employeeRepository);

        $entries = $this->createMock(TimeEntryRepository::class);
        $entries->method('findByEmployeeId')->willReturn([]);

        $tickets = $this->createMock(TicketRepository::class);
        $workItems = $this->createMock(WorkItemRepository::class);
        $workItems->method('findByAssignedUser')->willReturn([]);

        $logHandler = $this->createMock(LogTimeHandler::class);
        if (isset($options['log_handler'])) {
            $logHandler->method('handle')->willReturnCallback($options['log_handler']);
        } else {
            $logHandler->method('handle')->willReturn(123);
        }

        return new StaffTimeCaptureController(
            $flags,
            $resolver,
            $entries,
            $tickets,
            $workItems,
            $logHandler
        );
    }

    private function makeEmployee(int $id, int $wpUserId): Employee
    {
        return new Employee(
            $wpUserId,
            'Sam',
            'Tech',
            'sam@example.com',
            $id,
            'active'
        );
    }
}
