<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class Opportunity
{
    private string $id; // UUID
    private int $customerId;
    private ?int $leadId;
    private string $name;
    private string $stage;
    private float $estimatedValue;
    private ?string $currency;
    private ?\DateTimeImmutable $expectedCloseDate;
    private int $ownerId; // WP user ID
    private array $qualification; // JSON blob
    private ?string $notes;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $closedAt;
    private ?int $quoteId;

    public const STAGE_DISCOVERY    = 'discovery';
    public const STAGE_PROPOSAL     = 'proposal';
    public const STAGE_NEGOTIATION  = 'negotiation';
    public const STAGE_CLOSED_WON   = 'closed_won';
    public const STAGE_CLOSED_LOST  = 'closed_lost';

    public const STAGES = [
        self::STAGE_DISCOVERY,
        self::STAGE_PROPOSAL,
        self::STAGE_NEGOTIATION,
        self::STAGE_CLOSED_WON,
        self::STAGE_CLOSED_LOST,
    ];

    public const OPEN_STAGES = [
        self::STAGE_DISCOVERY,
        self::STAGE_PROPOSAL,
        self::STAGE_NEGOTIATION,
    ];

    public function __construct(
        string $id,
        int $customerId,
        string $name,
        string $stage = self::STAGE_DISCOVERY,
        float $estimatedValue = 0.0,
        int $ownerId = 0,
        ?int $leadId = null,
        ?string $currency = 'ZAR',
        ?\DateTimeImmutable $expectedCloseDate = null,
        array $qualification = [],
        ?string $notes = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $closedAt = null,
        ?int $quoteId = null
    ) {
        if (!in_array($stage, self::STAGES, true)) {
            throw new \DomainException("Invalid opportunity stage: '$stage'");
        }
        $this->id                = $id;
        $this->customerId        = $customerId;
        $this->leadId            = $leadId;
        $this->name              = $name;
        $this->stage             = $stage;
        $this->estimatedValue    = $estimatedValue;
        $this->currency          = $currency;
        $this->expectedCloseDate = $expectedCloseDate;
        $this->ownerId           = $ownerId;
        $this->qualification     = $qualification;
        $this->notes             = $notes;
        $this->createdAt         = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt         = $updatedAt;
        $this->closedAt          = $closedAt;
        $this->quoteId           = $quoteId;
    }

    public function id(): string { return $this->id; }
    public function customerId(): int { return $this->customerId; }
    public function leadId(): ?int { return $this->leadId; }
    public function name(): string { return $this->name; }
    public function stage(): string { return $this->stage; }
    public function estimatedValue(): float { return $this->estimatedValue; }
    public function currency(): ?string { return $this->currency; }
    public function expectedCloseDate(): ?\DateTimeImmutable { return $this->expectedCloseDate; }
    public function ownerId(): int { return $this->ownerId; }
    public function qualification(): array { return $this->qualification; }
    public function notes(): ?string { return $this->notes; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function closedAt(): ?\DateTimeImmutable { return $this->closedAt; }
    public function quoteId(): ?int { return $this->quoteId; }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::OPEN_STAGES, true);
    }

    public function update(
        string $name,
        string $stage,
        float $estimatedValue,
        ?string $currency,
        ?\DateTimeImmutable $expectedCloseDate,
        int $ownerId,
        array $qualification,
        ?string $notes
    ): void {
        if (!in_array($stage, self::STAGES, true)) {
            throw new \DomainException("Invalid opportunity stage: '$stage'");
        }
        $this->name              = $name;
        $this->stage             = $stage;
        $this->estimatedValue    = $estimatedValue;
        $this->currency          = $currency;
        $this->expectedCloseDate = $expectedCloseDate;
        $this->ownerId           = $ownerId;
        $this->qualification     = $qualification;
        $this->notes             = $notes;
        $this->updatedAt         = new \DateTimeImmutable();
    }

    public function close(string $stage): void
    {
        if (!in_array($stage, [self::STAGE_CLOSED_WON, self::STAGE_CLOSED_LOST], true)) {
            throw new \DomainException("Close stage must be 'closed_won' or 'closed_lost', got '$stage'");
        }
        $this->stage     = $stage;
        $this->closedAt  = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function linkQuote(int $quoteId): void
    {
        $this->quoteId   = $quoteId;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
