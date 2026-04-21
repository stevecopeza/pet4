<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Time\Command;

use Pet\Application\Time\Command\LogTimeCommand;
use Pet\Application\Time\Command\LogTimeHandler;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Tests\Stub\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

final class LogTimeHandlerTest extends TestCase
{
    public function testRejectsMissingTicketBeforePersistingTimeEntry(): void
    {
        $counter = new class {
            public int $savedEntries = 0;
        };

        $timeEntries = new class($counter) implements TimeEntryRepository {
            public function __construct(private object $counter)
            {
            }

            public function save(TimeEntry $timeEntry): void
            {
                $this->counter->savedEntries++;
            }

            public function findById(int $id): ?TimeEntry { return null; }
            public function findAll(): array { return []; }
            public function findByEmployeeId(int $employeeId): array { return []; }
            public function findByTicketId(int $ticketId): array { return []; }
            public function delete(int $id): void {}
            public function sumBillableHours(): float { return 0.0; }
        };

        $employee = $this->createMock(Employee::class);
        $employees = new class($employee) implements EmployeeRepository {
            public function __construct(private Employee $employee)
            {
            }

            public function save(Employee $employee): void {}
            public function findById(int $id): ?Employee { return $this->employee; }
            public function findByWpUserId(int $wpUserId): ?Employee { return null; }
            public function findAll(): array { return [$this->employee]; }
        };

        $tickets = new class implements TicketRepository {
            public function save(\Pet\Domain\Support\Entity\Ticket $ticket): void {}
            public function findById(int $id): ?\Pet\Domain\Support\Entity\Ticket { return null; }
            public function findAll(): array { return []; }
            public function findByCustomerId(int $customerId): array { return []; }
            public function findActive(): array { return []; }
            public function countActiveUnassigned(): int { return 0; }
            public function delete(int $id): void {}
            public function findByQuoteId(int $quoteId): array { return []; }
            public function findByProvisioningKey(int $projectId, int $sourceComponentId, ?int $parentTicketId): ?\Pet\Domain\Support\Entity\Ticket { return null; }
        };

        $handler = new LogTimeHandler(
            new FakeTransactionManager(),
            $timeEntries,
            $employees,
            $tickets
        );

        $command = new LogTimeCommand(
            1,
            9999,
            new \DateTimeImmutable('2026-03-01 10:00:00'),
            new \DateTimeImmutable('2026-03-01 11:00:00'),
            true,
            'Missing ticket safety test'
        );

        try {
            $handler->handle($command);
            self::fail('Expected DomainException for missing ticket.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('Ticket not found', $e->getMessage());
        }

        self::assertSame(0, $counter->savedEntries, 'No time entries should be saved when ticket does not exist.');
    }
}
