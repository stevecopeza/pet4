<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class ResolveRateCardQuery
{
    private int $roleId;
    private int $serviceTypeId;
    private ?int $contractId;
    private \DateTimeImmutable $effectiveDate;

    public function __construct(
        int $roleId,
        int $serviceTypeId,
        ?int $contractId,
        \DateTimeImmutable $effectiveDate
    ) {
        $this->roleId = $roleId;
        $this->serviceTypeId = $serviceTypeId;
        $this->contractId = $contractId;
        $this->effectiveDate = $effectiveDate;
    }

    public function roleId(): int { return $this->roleId; }
    public function serviceTypeId(): int { return $this->serviceTypeId; }
    public function contractId(): ?int { return $this->contractId; }
    public function effectiveDate(): \DateTimeImmutable { return $this->effectiveDate; }
}
