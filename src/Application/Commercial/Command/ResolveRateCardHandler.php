<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\Commercial\Service\RateCardResolver;
use Pet\Domain\Commercial\Entity\RateCard;

class ResolveRateCardHandler
{
    private RateCardResolver $resolver;

    public function __construct(RateCardResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function handle(ResolveRateCardQuery $query): RateCard
    {
        return $this->resolver->resolve(
            $query->roleId(),
            $query->serviceTypeId(),
            $query->contractId(),
            $query->effectiveDate()
        );
    }
}
