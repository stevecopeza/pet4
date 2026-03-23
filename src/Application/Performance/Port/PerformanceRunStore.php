<?php

declare(strict_types=1);

namespace Pet\Application\Performance\Port;

interface PerformanceRunStore
{
    public function createRun(string $runType, string $status, \DateTimeImmutable $startedAt): int;

    public function updateRun(int $runId, string $status, ?\DateTimeImmutable $completedAt, ?int $durationMs): void;

    /**
     * @return array<string,mixed>|null
     */
    public function findLatestByType(string $runType): ?array;

    /**
     * @return array<string,mixed>|null
     */
    public function findLatestMeaningfulByType(string $runType): ?array;
}

