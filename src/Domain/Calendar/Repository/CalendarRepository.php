<?php

declare(strict_types=1);

namespace Pet\Domain\Calendar\Repository;

use Pet\Domain\Calendar\Entity\Calendar;

interface CalendarRepository
{
    public function save(Calendar $calendar): void;
    public function findById(int $id): ?Calendar;
    public function findByUuid(string $uuid): ?Calendar;
    public function findDefault(): ?Calendar;
    public function findAll(): array;
    public function delete(int $id): void;
}
