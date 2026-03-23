<?php

declare(strict_types=1);

namespace Pet\Application\Performance\Service;

final class RecommendationEngine
{
    /**
     * @param array<int, array{metric_key:string, metric_value:mixed, context:array<string,mixed>|null}> $metrics
     * @return array<int, array<string,mixed>>
     */
    public function recommend(array $metrics): array
    {
        $indexed = $this->indexMetrics($metrics);
        $recommendations = [];

        $cacheBackend = $indexed['cache.backend']['metric_value'] ?? null;
        $cacheAvailable = $indexed['cache.available']['metric_value'] ?? null;
        if ($cacheBackend === 'none' || $cacheAvailable === false) {
            $recommendations[] = $this->recommendation(
                'no_object_cache',
                'CRITICAL',
                'Object cache is not available',
                'Enable Redis or Memcached object cache to reduce repeated database load.',
                'cache.backend',
                $cacheBackend
            );
        }

        $dbConnectionLatency = $this->asFloat($indexed['database.connection_latency_ms']['metric_value'] ?? null);
        if ($dbConnectionLatency !== null && $dbConnectionLatency >= 200.0) {
            $recommendations[] = $this->recommendation(
                'high_db_latency',
                'HIGH',
                'Database connection latency is high',
                'Investigate database host latency and connection overhead.',
                'database.connection_latency_ms',
                $dbConnectionLatency
            );
        }

        $cacheHitRate = $this->asFloat($indexed['cache.hit_rate']['metric_value'] ?? null);
        $cacheStatsAvailable = $indexed['cache.stats_available']['metric_value'] ?? null;
        if ($cacheStatsAvailable === true && $cacheHitRate !== null && $cacheHitRate < 80.0) {
            $recommendations[] = $this->recommendation(
                'low_cache_hit_rate',
                'WARNING',
                'Cache hit rate is below target',
                'Review cache key strategy and cache invalidation behaviour.',
                'cache.hit_rate',
                $cacheHitRate
            );
        }

        return $recommendations;
    }

    /**
     * @param array<int, array{metric_key:string, metric_value:mixed, context:array<string,mixed>|null}> $metrics
     * @return array<string, array{metric_key:string, metric_value:mixed, context:array<string,mixed>|null}>
     */
    private function indexMetrics(array $metrics): array
    {
        $indexed = [];
        foreach ($metrics as $metric) {
            $key = (string) ($metric['metric_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $indexed[$key] = $metric;
        }
        return $indexed;
    }

    private function asFloat($value): ?float
    {
        if (\is_int($value) || \is_float($value)) {
            return (float) $value;
        }
        if (\is_string($value) && \is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }

    private function recommendation(
        string $issueKey,
        string $severity,
        string $title,
        string $message,
        string $metricKey,
        $observedValue
    ): array {
        return [
            'issue_key' => $issueKey,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'metric_key' => $metricKey,
            'observed_value' => $observedValue,
        ];
    }
}

