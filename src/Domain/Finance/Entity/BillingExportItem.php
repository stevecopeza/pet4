<?php

declare(strict_types=1);

namespace Pet\Domain\Finance\Entity;

final class BillingExportItem
{
    private int $id;
    private int $exportId;
    private string $sourceType;
    private int $sourceId;
    private float $quantity;
    private float $unitPrice;
    private float $amount;
    private string $description;
    private ?string $qbItemRef;
    private string $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $id,
        int $exportId,
        string $sourceType,
        int $sourceId,
        float $quantity,
        float $unitPrice,
        float $amount,
        string $description,
        ?string $qbItemRef,
        string $status,
        \DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->exportId = $exportId;
        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->amount = $amount;
        $this->description = $description;
        $this->qbItemRef = $qbItemRef;
        $this->status = $status;
        $this->createdAt = $createdAt;
    }

    public static function pending(
        int $exportId,
        string $sourceType,
        int $sourceId,
        float $quantity,
        float $unitPrice,
        string $description,
        ?string $qbItemRef
    ): self {
        $amount = round($quantity * $unitPrice, 2);
        return new self(
            0,
            $exportId,
            $sourceType,
            $sourceId,
            $quantity,
            $unitPrice,
            $amount,
            $description,
            $qbItemRef,
            'pending',
            new \DateTimeImmutable()
        );
    }

    public function id(): int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function exportId(): int { return $this->exportId; }
    public function sourceType(): string { return $this->sourceType; }
    public function sourceId(): int { return $this->sourceId; }
    public function quantity(): float { return $this->quantity; }
    public function unitPrice(): float { return $this->unitPrice; }
    public function amount(): float { return $this->amount; }
    public function description(): string { return $this->description; }
    public function qbItemRef(): ?string { return $this->qbItemRef; }
    public function status(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
}
