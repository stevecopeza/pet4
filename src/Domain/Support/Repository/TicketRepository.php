<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Repository;

use Pet\Domain\Support\Entity\Ticket;

interface TicketRepository
{
    public function save(Ticket $ticket): void;
    public function findById(int $id): ?Ticket;
    public function findAll(): array;
    public function findByCustomerId(int $customerId): array;
    public function findActive(): array;
    public function countActiveUnassigned(): int;
    public function delete(int $id): void;
}
