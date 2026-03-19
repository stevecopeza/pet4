<?php

declare(strict_types=1);

namespace Pet\Domain\Advisory\Entity;

use DateTimeImmutable;

class AdvisoryReport
{
    public function __construct(
        private string $id,
        private string $reportType,
        private string $scopeType,
        private int $scopeId,
        private int $versionNumber,
        private string $title,
        private ?string $summary,
        private string $status,
        private DateTimeImmutable $generatedAt,
        private ?int $generatedBy,
        private array $content,
        private ?array $sourceSnapshotMetadata = null
    ) {
    }

    public function id(): string { return $this->id; }
    public function reportType(): string { return $this->reportType; }
    public function scopeType(): string { return $this->scopeType; }
    public function scopeId(): int { return $this->scopeId; }
    public function versionNumber(): int { return $this->versionNumber; }
    public function title(): string { return $this->title; }
    public function summary(): ?string { return $this->summary; }
    public function status(): string { return $this->status; }
    public function generatedAt(): DateTimeImmutable { return $this->generatedAt; }
    public function generatedBy(): ?int { return $this->generatedBy; }
    public function content(): array { return $this->content; }
    public function sourceSnapshotMetadata(): ?array { return $this->sourceSnapshotMetadata; }
}

