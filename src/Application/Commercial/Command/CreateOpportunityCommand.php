<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class CreateOpportunityCommand
{
    public function __construct(
        private int $customerId,
        private string $name,
        private string $stage,
        private float $estimatedValue,
        private int $ownerId,
        private ?int $leadId = null,
        private ?string $currency = 'ZAR',
        private ?string $expectedCloseDate = null,
        private array $qualification = [],
        private ?string $notes = null
    ) {}

    public function customerId(): int { return $this->customerId; }
    public function name(): string { return $this->name; }
    public function stage(): string { return $this->stage; }
    public function estimatedValue(): float { return $this->estimatedValue; }
    public function ownerId(): int { return $this->ownerId; }
    public function leadId(): ?int { return $this->leadId; }
    public function currency(): ?string { return $this->currency; }
    public function expectedCloseDate(): ?string { return $this->expectedCloseDate; }
    public function qualification(): array { return $this->qualification; }
    public function notes(): ?string { return $this->notes; }
}
