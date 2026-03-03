<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Repository;

use Pet\Domain\Support\Entity\TierTransition;

interface TierTransitionRepository
{
    public function save(TierTransition $transition): void;

    /**
     * @return TierTransition[]
     */
    public function findByTicketId(int $ticketId): array;
}
