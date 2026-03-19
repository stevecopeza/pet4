<?php

declare(strict_types=1);

namespace Pet\Domain\Resilience\Entity;

use DateTimeImmutable;

class ResilienceAnalysisRun
{
    public function __construct(
        private string $id,
        private string $scopeType,
        private int $scopeId,
        private int $versionNumber,
        private string $status,
        private DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $completedAt = null,
        private ?int $generatedBy = null,
        private ?array $summary = null
    ) {
    }

    public function id(): string { return $this->id; }
    public function scopeType(): string { return $this->scopeType; }
    public function scopeId(): int { return $this->scopeId; }
    public function versionNumber(): int { return $this->versionNumber; }
    public function status(): string { return $this->status; }
    public function startedAt(): DateTimeImmutable { return $this->startedAt; }
    public function completedAt(): ?DateTimeImmutable { return $this->completedAt; }
    public function generatedBy(): ?int { return $this->generatedBy; }
    public function summary(): ?array { return $this->summary; }
}

