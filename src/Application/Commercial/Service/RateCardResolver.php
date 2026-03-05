<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Service;

use Pet\Domain\Commercial\Entity\RateCard;
use Pet\Domain\Commercial\Repository\RateCardRepository;

/**
 * Resolves the applicable rate card for a given role + serviceType + optional contract scope.
 * Lives in Application layer (not Domain) because it depends on repository access.
 *
 * Resolution algorithm:
 * 1. If contractId is provided, try contract-specific rate card first
 * 2. If no contract-specific match, fall back to global (contractId = null)
 * 3. If no match found, throw DomainException
 */
class RateCardResolver
{
    private RateCardRepository $rateCardRepository;

    public function __construct(RateCardRepository $rateCardRepository)
    {
        $this->rateCardRepository = $rateCardRepository;
    }

    /**
     * @throws \DomainException when no valid rate card found
     */
    public function resolve(
        int $roleId,
        int $serviceTypeId,
        ?int $contractId,
        \DateTimeImmutable $effectiveDate
    ): RateCard {
        // 1. Try contract-specific if contractId provided
        if ($contractId !== null) {
            $card = $this->rateCardRepository->findForResolution(
                $roleId,
                $serviceTypeId,
                $contractId,
                $effectiveDate
            );
            if ($card !== null) {
                return $card;
            }
        }

        // 2. Fall back to global (contractId = null)
        $card = $this->rateCardRepository->findForResolution(
            $roleId,
            $serviceTypeId,
            null,
            $effectiveDate
        );

        if ($card !== null) {
            return $card;
        }

        throw new \DomainException(sprintf(
            'No valid rate card for role [%d] + serviceType [%d]%s at date %s',
            $roleId,
            $serviceTypeId,
            $contractId !== null ? " (contract {$contractId} or global)" : '',
            $effectiveDate->format('Y-m-d')
        ));
    }
}
