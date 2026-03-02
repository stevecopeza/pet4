<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

final class AddBillingExportItemCommand
{
    private int $exportId;
    private string $sourceType;
    private int $sourceId;
    private float $quantity;
    private float $unitPrice;
    private string $description;
    private ?string $qbItemRef;

    public function __construct(
        int $exportId,
        string $sourceType,
        int $sourceId,
        float $quantity,
        float $unitPrice,
        string $description,
        ?string $qbItemRef
    ) {
        $this->exportId = $exportId;
        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->description = $description;
        $this->qbItemRef = $qbItemRef;
    }

    public function exportId(): int { return $this->exportId; }
    public function sourceType(): string { return $this->sourceType; }
    public function sourceId(): int { return $this->sourceId; }
    public function quantity(): float { return $this->quantity; }
    public function unitPrice(): float { return $this->unitPrice; }
    public function description(): string { return $this->description; }
    public function qbItemRef(): ?string { return $this->qbItemRef; }
}
