<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Performance;

class CacheInspectorService
{
    public function inspect(): array
    {
        $metrics = [];
        $errors = [];

        $isExtObjectCache = \function_exists('wp_using_ext_object_cache') ? (bool) \wp_using_ext_object_cache() : false;
        $backend = 'none';
        $backendClass = null;
        $available = $isExtObjectCache;
        $hits = null;
        $misses = null;
        $hitRate = null;
        $statsAvailable = false;

        global $wp_object_cache;
        if (\is_object($wp_object_cache)) {
            $backendClass = \get_class($wp_object_cache);
            $backend = $this->detectBackend($backendClass);
            if ($backend !== 'none') {
                $available = true;
            }
            $stats = $this->extractStats($wp_object_cache);
            if ($stats['hits'] !== null && $stats['misses'] !== null) {
                $hits = $stats['hits'];
                $misses = $stats['misses'];
                $denominator = $hits + $misses;
                $hitRate = $denominator > 0 ? \round(($hits / $denominator) * 100, 3) : null;
                $statsAvailable = true;
            } elseif ($stats['error'] !== null) {
                $errors[] = [
                    'metric_key' => 'cache.stats',
                    'message' => $stats['error'],
                ];
            }
        }

        $metrics[] = $this->metric('cache.backend', $backend, [
            'class' => $backendClass,
            'capability_detection' => true,
        ]);
        $metrics[] = $this->metric('cache.available', $available, [
            'ext_object_cache' => $isExtObjectCache,
            'class_detected' => $backendClass !== null,
        ]);
        $metrics[] = $this->metric('cache.stats_available', $statsAvailable, [
            'fallback_mode' => !$statsAvailable,
        ]);
        $metrics[] = $this->metric('cache.hits', $hits, [
            'available' => $statsAvailable,
        ]);
        $metrics[] = $this->metric('cache.misses', $misses, [
            'available' => $statsAvailable,
        ]);
        $metrics[] = $this->metric('cache.hit_rate', $hitRate, [
            'available' => $statsAvailable,
            'unit' => 'percent',
        ]);

        return [
            'metrics' => $metrics,
            'errors' => $errors,
        ];
    }

    private function detectBackend(?string $className): string
    {
        if ($className === null || $className === '') {
            return 'none';
        }

        $normalized = \strtolower($className);
        if (\strpos($normalized, 'redis') !== false) {
            return 'redis';
        }
        if (\strpos($normalized, 'memcache') !== false) {
            return 'memcached';
        }
        if (\strpos($normalized, 'object_cache') !== false || \strpos($normalized, 'wp_object_cache') !== false) {
            return 'none';
        }

        return 'other';
    }

    private function extractStats(object $cacheObject): array
    {
        try {
            $hits = $this->readIntProperty($cacheObject, ['cache_hits', 'hits', 'get_hits']);
            $misses = $this->readIntProperty($cacheObject, ['cache_misses', 'misses', 'get_misses']);
            return [
                'hits' => $hits,
                'misses' => $misses,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'hits' => null,
                'misses' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function readIntProperty(object $cacheObject, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            if (\property_exists($cacheObject, $candidate)) {
                $value = $cacheObject->{$candidate};
                if (\is_numeric($value)) {
                    return (int) $value;
                }
            }
            if (\method_exists($cacheObject, $candidate)) {
                $value = $cacheObject->{$candidate}();
                if (\is_numeric($value)) {
                    return (int) $value;
                }
            }
        }
        return null;
    }

    private function metric(string $metricKey, $metricValue, array $context): array
    {
        return [
            'metric_key' => $metricKey,
            'metric_value' => $metricValue,
            'context' => $context,
        ];
    }
}

