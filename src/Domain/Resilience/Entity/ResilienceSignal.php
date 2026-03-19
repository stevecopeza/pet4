<?php

declare(strict_types=1);

namespace Pet\Domain\Resilience\Entity;

use DateTimeImmutable;

class ResilienceSignal
{
    public const TYPE_UTILIZATION_OVERLOAD = 'utilization_overload';
    public const TYPE_TEAM_SPOF = 'team_spof';
    public const TYPE_WORKLOAD_CONCENTRATION = 'workload_concentration';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        private string $id,
        private string $analysisRunId,
        private string $scopeType,
        private int $scopeId,
        private string $signalType,
        private string $severity,
        private string $title,
        private string $summary,
        private DateTimeImmutable $createdAt,
        private ?int $employeeId = null,
        private ?int $teamId = null,
        private ?int $roleId = null,
        private ?string $sourceEntityType = null,
        private ?string $sourceEntityId = null,
        private ?string $status = 'ACTIVE',
        private ?DateTimeImmutable $resolvedAt = null,
        private ?array $metadata = null
    ) {}

    public function id(): string { return $this->id; }
    public function analysisRunId(): string { return $this->analysisRunId; }
    public function scopeType(): string { return $this->scopeType; }
    public function scopeId(): int { return $this->scopeId; }
    public function signalType(): string { return $this->signalType; }
    public function severity(): string { return $this->severity; }
    public function title(): string { return $this->title; }
    public function summary(): string { return $this->summary; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function employeeId(): ?int { return $this->employeeId; }
    public function teamId(): ?int { return $this->teamId; }
    public function roleId(): ?int { return $this->roleId; }
    public function sourceEntityType(): ?string { return $this->sourceEntityType; }
    public function sourceEntityId(): ?string { return $this->sourceEntityId; }
    public function status(): string { return $this->status ?? 'ACTIVE'; }
    public function resolvedAt(): ?DateTimeImmutable { return $this->resolvedAt; }
    public function metadata(): ?array { return $this->metadata; }
}

