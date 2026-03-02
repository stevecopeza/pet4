<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

use Pet\Domain\Commercial\ValueObject\ContractStatus;

class Contract
{
    private ?int $id;
    private int $quoteId;
    private int $customerId;
    private ContractStatus $status;
    private float $totalValue;
    private string $currency;
    private \DateTimeImmutable $startDate;
    private ?\DateTimeImmutable $endDate;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        int $quoteId,
        int $customerId,
        ContractStatus $status,
        float $totalValue,
        string $currency,
        \DateTimeImmutable $startDate,
        ?int $id = null,
        ?\DateTimeImmutable $endDate = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->quoteId = $quoteId;
        $this->customerId = $customerId;
        $this->status = $status;
        $this->totalValue = $totalValue;
        $this->currency = $currency;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function status(): ContractStatus
    {
        return $this->status;
    }

    public function totalValue(): float
    {
        return $this->totalValue;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function startDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function complete(\DateTimeImmutable $endDate): void
    {
        $this->transitionTo(ContractStatus::completed());
        $this->endDate = $endDate;
    }

    public function terminate(\DateTimeImmutable $endDate): void
    {
        $this->transitionTo(ContractStatus::terminated());
        $this->endDate = $endDate;
    }

    private function transitionTo(ContractStatus $newState): void
    {
        if (!$this->status->canTransitionTo($newState)) {
            throw new \DomainException(sprintf(
                'Invalid state transition from %s to %s',
                $this->status->toString(),
                $newState->toString()
            ));
        }

        $this->status = $newState;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
