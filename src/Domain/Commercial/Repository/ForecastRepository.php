<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\Forecast;

interface ForecastRepository
{
    public function save(Forecast $forecast): void;
    public function findByQuoteId(int $quoteId): ?Forecast;
    public function findAll(): array;
}
