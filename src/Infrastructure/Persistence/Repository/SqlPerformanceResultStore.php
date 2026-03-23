<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Application\Performance\Port\PerformanceResultStore;

class SqlPerformanceResultStore implements PerformanceResultStore
{
    private \wpdb $wpdb;
    private string $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_performance_results';
    }

    /**
     * @param array<string,mixed>|null $context
     */
    public function saveMetric(int $runId, string $metricKey, $metricValue, ?array $context = null): void
    {
        $this->wpdb->insert(
            $this->tableName,
            [
                'run_id' => $runId,
                'metric_key' => $metricKey,
                'metric_value' => $this->encodeValue($metricValue),
                'context_json' => $context !== null
                    ? \wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null,
            ]
        );
    }

    /**
     * @param array<int, array{metric_key:string, metric_value:mixed, context:array<string,mixed>|null}> $rows
     */
    public function saveMetrics(int $runId, array $rows): void
    {
        foreach ($rows as $row) {
            $this->saveMetric(
                $runId,
                (string) ($row['metric_key'] ?? ''),
                $row['metric_value'] ?? null,
                isset($row['context']) && \is_array($row['context']) ? $row['context'] : null
            );
        }
    }

    /**
     * @return array<int, array{id:int, run_id:int, metric_key:string, metric_value:mixed, context:array<string,mixed>|null}>
     */
    public function findByRunId(int $runId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, run_id, metric_key, metric_value, context_json
                 FROM {$this->tableName}
                 WHERE run_id = %d
                 ORDER BY id ASC",
                $runId
            ),
            ARRAY_A
        );

        if (!\is_array($rows)) {
            return [];
        }

        return \array_map(function (array $row): array {
            $context = null;
            if (isset($row['context_json']) && \is_string($row['context_json']) && $row['context_json'] !== '') {
                $decoded = \json_decode($row['context_json'], true);
                $context = \is_array($decoded) ? $decoded : null;
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'run_id' => (int) ($row['run_id'] ?? 0),
                'metric_key' => (string) ($row['metric_key'] ?? ''),
                'metric_value' => $this->decodeValue(isset($row['metric_value']) ? (string) $row['metric_value'] : ''),
                'context' => $context,
            ];
        }, $rows);
    }

    private function encodeValue($metricValue): string
    {
        return \wp_json_encode($metricValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function decodeValue(string $encoded)
    {
        $decoded = \json_decode($encoded, true);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            return $encoded;
        }
        return $decoded;
    }
}

