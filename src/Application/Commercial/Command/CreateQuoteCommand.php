<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class CreateQuoteCommand
{
    private int $customerId;
    private string $title;
    private ?string $description;
    private string $currency;
    private ?\DateTimeImmutable $acceptedAt;
    private array $malleableData;
    private ?int $leadId;
    private ?int $createdByUserId;

    public function __construct(
        int $customerId,
        string $title,
        ?string $description,
        string $currency = 'USD',
        ?\DateTimeImmutable $acceptedAt = null,
        array $malleableData = [],
        ?int $leadId = null,
        ?int $createdByUserId = null
    ) {
        $this->customerId = $customerId;
        $this->title = $title;
        $this->description = $description;
        $this->currency = $currency;
        $this->acceptedAt = $acceptedAt;
        $this->malleableData = $malleableData;
        $this->leadId = $leadId;
        $this->createdByUserId = $createdByUserId;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function acceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }

    public function leadId(): ?int
    {
        return $this->leadId;
    }

    public function createdByUserId(): ?int
    {
        return $this->createdByUserId;
    }
}
