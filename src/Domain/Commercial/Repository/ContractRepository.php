<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\Contract;

interface ContractRepository
{
    public function save(Contract $contract): void;
    public function findById(int $id): ?Contract;
    public function findByQuoteId(int $quoteId): ?Contract;
}
