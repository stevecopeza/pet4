<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Performance;

class PhpRuntimeInspector
{
    public function inspect(): array
    {
        $metrics = [];
        $errors = [];

        $opcacheEnabled = \function_exists('opcache_get_status') && (bool) \ini_get('opcache.enable');
        $metrics[] = $this->metric('php.opcache_enabled', $opcacheEnabled, ['source' => 'opcache']);

        if (!$opcacheEnabled) {
            $metrics[] = $this->metric('php.opcache_memory_used_bytes', null, ['available' => false]);
            $metrics[] = $this->metric('php.opcache_memory_free_bytes', null, ['available' => false]);
            $metrics[] = $this->metric('php.opcache_hit_rate', null, ['available' => false]);
            return [
                'metrics' => $metrics,
                'errors' => $errors,
            ];
        }

        try {
            $status = \opcache_get_status(false);
            if (!\is_array($status)) {
                throw new \RuntimeException('opcache_status_unavailable');
            }

            $memory = isset($status['memory_usage']) && \is_array($status['memory_usage']) ? $status['memory_usage'] : [];
            $stats = isset($status['opcache_statistics']) && \is_array($status['opcache_statistics']) ? $status['opcache_statistics'] : [];

            $used = isset($memory['used_memory']) && \is_numeric($memory['used_memory']) ? (int) $memory['used_memory'] : null;
            $free = isset($memory['free_memory']) && \is_numeric($memory['free_memory']) ? (int) $memory['free_memory'] : null;
            $hitRate = isset($stats['opcache_hit_rate']) && \is_numeric($stats['opcache_hit_rate']) ? (float) $stats['opcache_hit_rate'] : null;

            $metrics[] = $this->metric('php.opcache_memory_used_bytes', $used, [
                'available' => $used !== null,
            ]);
            $metrics[] = $this->metric('php.opcache_memory_free_bytes', $free, [
                'available' => $free !== null,
            ]);
            $metrics[] = $this->metric('php.opcache_hit_rate', $hitRate, [
                'available' => $hitRate !== null,
                'unit' => 'percent',
            ]);
        } catch (\Throwable $e) {
            $errors[] = [
                'metric_key' => 'php.opcache',
                'message' => $e->getMessage(),
            ];
            $metrics[] = $this->metric('php.opcache_memory_used_bytes', null, ['available' => false]);
            $metrics[] = $this->metric('php.opcache_memory_free_bytes', null, ['available' => false]);
            $metrics[] = $this->metric('php.opcache_hit_rate', null, ['available' => false]);
        }

        return [
            'metrics' => $metrics,
            'errors' => $errors,
        ];
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

