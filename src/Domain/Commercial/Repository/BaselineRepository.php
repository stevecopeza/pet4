<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\Baseline;

interface BaselineRepository
{
    public function save(Baseline $baseline): void;
    public function findByContractId(int $contractId): ?Baseline;
}
