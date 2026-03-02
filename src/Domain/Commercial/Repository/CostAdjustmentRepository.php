<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\CostAdjustment;

interface CostAdjustmentRepository
{
    public function save(CostAdjustment $adjustment): void;
    public function findByQuoteId(int $quoteId): array;
    public function delete(int $id): void;
}
