<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class RateCard
{
    private ?int $id;
    private int $roleId;
    private int $serviceTypeId;
    private float $sellRate;
    private ?int $contractId;
    private ?\DateTimeImmutable $validFrom;
    private ?\DateTimeImmutable $validTo;
    private string $status;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        int $roleId,
        int $serviceTypeId,
        float $sellRate,
        ?int $contractId = null,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null,
        string $status = 'active',
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        if ($sellRate <= 0) {
            throw new \InvalidArgumentException('RateCard sell rate must be greater than 0.');
        }
        if ($validFrom !== null && $validTo !== null && $validFrom > $validTo) {
            throw new \InvalidArgumentException('RateCard validFrom must be <= validTo when both are set.');
        }
        if (!in_array($status, ['active', 'archived'], true)) {
            throw new \InvalidArgumentException("Invalid RateCard status: {$status}");
        }

        $this->roleId = $roleId;
        $this->serviceTypeId = $serviceTypeId;
        $this->sellRate = $sellRate;
        $this->contractId = $contractId;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
        $this->status = $status;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function serviceTypeId(): int
    {
        return $this->serviceTypeId;
    }

    public function sellRate(): float
    {
        return $this->sellRate;
    }

    public function contractId(): ?int
    {
        return $this->contractId;
    }

    public function validFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if this rate card is effective at a given date.
     * NULL validFrom = open start (effective from the beginning of time).
     * NULL validTo = open end (effective indefinitely).
     */
    public function isEffectiveAt(\DateTimeImmutable $date): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->validFrom !== null && $date < $this->validFrom) {
            return false;
        }
        if ($this->validTo !== null && $date > $this->validTo) {
            return false;
        }
        return true;
    }

    public function archive(): void
    {
        if ($this->status === 'archived') {
            throw new \DomainException('RateCard is already archived.');
        }
        $this->status = 'archived';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
