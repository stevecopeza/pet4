<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\RateCard;

interface RateCardRepository
{
    public function findById(int $id): ?RateCard;

    /** @return RateCard[] */
    public function findAll(array $filters = []): array;

    public function save(RateCard $rateCard): void;

    public function archive(int $id): void;

    /**
     * Find rate cards that overlap with a given date range for the same role/serviceType/contract scope.
     * Used for overlap validation when creating new rate cards.
     *
     * @return RateCard[]
     */
    public function findOverlapping(
        int $roleId,
        int $serviceTypeId,
        ?int $contractId,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo,
        ?int $excludeId = null
    ): array;

    /**
     * Find the best matching rate card for resolution at a given effective date.
     * Returns active rate card matching role+serviceType+contract where effectiveDate falls within range.
     */
    public function findForResolution(
        int $roleId,
        int $serviceTypeId,
        ?int $contractId,
        \DateTimeImmutable $effectiveDate
    ): ?RateCard;
}
