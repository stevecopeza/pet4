<?php

declare(strict_types=1);

namespace Pet\Domain\Time\Repository;

use Pet\Domain\Time\Entity\TimeEntry;

interface TimeEntryRepository
{
    public function save(TimeEntry $timeEntry): void;
    public function findById(int $id): ?TimeEntry;
    /** @return TimeEntry[] */
    public function findAll(): array;
    /** @return TimeEntry[] */
    public function findByEmployeeId(int $employeeId): array;
    /** @return TimeEntry[] */
    public function findByTicketId(int $ticketId): array;
    public function delete(int $id): void;

    public function sumBillableHours(): float;
}
