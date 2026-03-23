<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Performance;

class PetWorkloadProfiler
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function begin(bool $isActiveRun): ?array
    {
        if (!$isActiveRun) {
            return null;
        }

        return [
            'started_at' => \microtime(true),
            'query_count_start' => $this->queryCount(),
        ];
    }

    public function end(string $workloadKey, ?array $token): ?array
    {
        if ($token === null) {
            return null;
        }

        $queryStart = isset($token['query_count_start']) && \is_numeric($token['query_count_start'])
            ? (int) $token['query_count_start']
            : 0;

        $startedAt = isset($token['started_at']) && \is_numeric($token['started_at'])
            ? (float) $token['started_at']
            : \microtime(true);

        $queryDelta = $this->queryCount() - $queryStart;
        if ($queryDelta < 0) {
            $queryDelta = 0;
        }

        $elapsedMs = \round((\microtime(true) - $startedAt) * 1000, 3);

        return [
            'workload_key' => $workloadKey,
            'query_count' => $queryDelta,
            'execution_time_ms' => $elapsedMs,
        ];
    }

    private function queryCount(): int
    {
        if (\property_exists($this->wpdb, 'num_queries') && \is_numeric($this->wpdb->num_queries)) {
            return (int) $this->wpdb->num_queries;
        }

        if (\defined('SAVEQUERIES') && SAVEQUERIES && \property_exists($this->wpdb, 'queries') && \is_array($this->wpdb->queries)) {
            return \count($this->wpdb->queries);
        }

        return 0;
    }
}

