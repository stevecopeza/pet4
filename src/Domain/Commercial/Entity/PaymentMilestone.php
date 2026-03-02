<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class PaymentMilestone
{
    private ?int $id;
    private string $title;
    private float $amount;
    private ?\DateTimeImmutable $dueDate;
    private bool $paid;

    public function __construct(
        string $title, 
        float $amount, 
        ?\DateTimeImmutable $dueDate = null,
        bool $paid = false,
        ?int $id = null
    ) {
        if ($amount < 0) {
            throw new \DomainException("Payment milestone amount cannot be negative");
        }
        $this->title = $title;
        $this->amount = $amount;
        $this->dueDate = $dueDate;
        $this->paid = $paid;
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }
    
    public function isPaid(): bool
    {
        return $this->paid;
    }
}
