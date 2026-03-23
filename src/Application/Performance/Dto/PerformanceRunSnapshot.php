<?php

declare(strict_types=1);

namespace Pet\Application\Performance\Dto;

final class PerformanceRunSnapshot
{
    /**
     * @param array<int, array<string,mixed>> $recommendations
     */
    public function __construct(
        private int $runId,
        private string $runType,
        private string $status,
        private \DateTimeImmutable $startedAt,
        private ?\DateTimeImmutable $completedAt,
        private ?int $durationMs,
        private int $metricCount,
        private int $errorCount,
        private array $recommendations = [],
        private ?int $cooldownRemainingSeconds = null
    ) {
    }

    public function runId(): int
    {
        return $this->runId;
    }

    public function runType(): string
    {
        return $this->runType;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function durationMs(): ?int
    {
        return $this->durationMs;
    }

    public function metricCount(): int
    {
        return $this->metricCount;
    }

    public function errorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function recommendations(): array
    {
        return $this->recommendations;
    }

    public function cooldownRemainingSeconds(): ?int
    {
        return $this->cooldownRemainingSeconds;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'run_type' => $this->runType,
            'status' => $this->status,
            'started_at' => $this->startedAt->format('c'),
            'completed_at' => $this->completedAt?->format('c'),
            'duration_ms' => $this->durationMs,
            'metric_count' => $this->metricCount,
            'error_count' => $this->errorCount,
            'recommendations' => $this->recommendations,
            'cooldown_remaining_seconds' => $this->cooldownRemainingSeconds,
        ];
    }
}

