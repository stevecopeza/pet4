<?php

declare(strict_types=1);

namespace Pet\Domain\Advisory\Entity;

use DateTimeImmutable;

class AdvisorySignal
{
    public const TYPE_SLA_RISK = 'sla_risk';
    public const TYPE_DEADLINE_RISK = 'deadline_risk';
    public const TYPE_IDLE_HIGH_PRIORITY = 'idle_high_priority';
    public const TYPE_CAPACITY_BOTTLENECK = 'capacity_bottleneck';
    public const TYPE_CONTEXT_SWITCHING = 'context_switching';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        private string $id,
        private string $workItemId,
        private string $signalType,
        private string $severity,
        private string $message,
        private DateTimeImmutable $createdAt,
        private ?string $status = 'ACTIVE',
        private ?DateTimeImmutable $resolvedAt = null,
        private ?string $generationRunId = null,
        private ?string $title = null,
        private ?string $summary = null,
        private ?array $metadata = null,
        private ?string $sourceEntityType = null,
        private ?string $sourceEntityId = null,
        private ?int $customerId = null,
        private ?int $siteId = null
    ) {}

    public function getId(): string { return $this->id; }
    public function getWorkItemId(): string { return $this->workItemId; }
    public function getSignalType(): string { return $this->signalType; }
    public function getSeverity(): string { return $this->severity; }
    public function getMessage(): string { return $this->message; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getStatus(): string { return $this->status ?? 'ACTIVE'; }
    public function getResolvedAt(): ?DateTimeImmutable { return $this->resolvedAt; }
    public function getGenerationRunId(): ?string { return $this->generationRunId; }
    public function getTitle(): ?string { return $this->title; }
    public function getSummary(): ?string { return $this->summary; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function getSourceEntityType(): ?string { return $this->sourceEntityType; }
    public function getSourceEntityId(): ?string { return $this->sourceEntityId; }
    public function getCustomerId(): ?int { return $this->customerId; }
    public function getSiteId(): ?int { return $this->siteId; }
}
