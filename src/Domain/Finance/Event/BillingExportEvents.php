<?php

declare(strict_types=1);

namespace Pet\Domain\Finance\Event;

use Pet\Domain\Event\DomainEvent;

final class BillingExportCreated implements DomainEvent
{
    public function __construct(
        private string $uuid,
        private int $exportId,
        private int $customerId,
        private \DateTimeImmutable $periodStart,
        private \DateTimeImmutable $periodEnd,
        private int $createdByEmployeeId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {}
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function uuid(): string { return $this->uuid; }
    public function exportId(): int { return $this->exportId; }
    public function customerId(): int { return $this->customerId; }
    public function periodStart(): \DateTimeImmutable { return $this->periodStart; }
    public function periodEnd(): \DateTimeImmutable { return $this->periodEnd; }
    public function createdByEmployeeId(): int { return $this->createdByEmployeeId; }
}

final class BillingExportItemAdded implements DomainEvent
{
    public function __construct(
        private int $exportId,
        private int $itemId,
        private string $sourceType,
        private int $sourceId,
        private float $quantity,
        private float $unitPrice,
        private float $amount,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {}
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function exportId(): int { return $this->exportId; }
    public function itemId(): int { return $this->itemId; }
    public function sourceType(): string { return $this->sourceType; }
    public function sourceId(): int { return $this->sourceId; }
    public function quantity(): float { return $this->quantity; }
    public function unitPrice(): float { return $this->unitPrice; }
    public function amount(): float { return $this->amount; }
}

final class BillingExportQueued implements DomainEvent
{
    public function __construct(
        private int $exportId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {}
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function exportId(): int { return $this->exportId; }
}

final class BillingExportSent implements DomainEvent
{
    public function __construct(
        private int $exportId,
        private string $qbInvoiceId,
        private string $docNumber,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {}
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function exportId(): int { return $this->exportId; }
    public function qbInvoiceId(): string { return $this->qbInvoiceId; }
    public function docNumber(): string { return $this->docNumber; }
}

final class BillingExportFailed implements DomainEvent
{
    public function __construct(
        private int $exportId,
        private string $reason,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {}
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function exportId(): int { return $this->exportId; }
    public function reason(): string { return $this->reason; }
}

final class BillingExportConfirmed implements DomainEvent
{
    public function __construct(
        private int $exportId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {}
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function exportId(): int { return $this->exportId; }
}

