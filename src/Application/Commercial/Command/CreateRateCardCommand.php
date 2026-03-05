<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class CreateRateCardCommand
{
    private int $roleId;
    private int $serviceTypeId;
    private float $sellRate;
    private ?int $contractId;
    private ?\DateTimeImmutable $validFrom;
    private ?\DateTimeImmutable $validTo;

    public function __construct(
        int $roleId,
        int $serviceTypeId,
        float $sellRate,
        ?int $contractId = null,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null
    ) {
        $this->roleId = $roleId;
        $this->serviceTypeId = $serviceTypeId;
        $this->sellRate = $sellRate;
        $this->contractId = $contractId;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    public function roleId(): int { return $this->roleId; }
    public function serviceTypeId(): int { return $this->serviceTypeId; }
    public function sellRate(): float { return $this->sellRate; }
    public function contractId(): ?int { return $this->contractId; }
    public function validFrom(): ?\DateTimeImmutable { return $this->validFrom; }
    public function validTo(): ?\DateTimeImmutable { return $this->validTo; }
}
