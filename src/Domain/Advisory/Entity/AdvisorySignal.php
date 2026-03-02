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
        private DateTimeImmutable $createdAt
    ) {}

    public function getId(): string { return $this->id; }
    public function getWorkItemId(): string { return $this->workItemId; }
    public function getSignalType(): string { return $this->signalType; }
    public function getSeverity(): string { return $this->severity; }
    public function getMessage(): string { return $this->message; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
