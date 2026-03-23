<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Performance;

class DatabaseProfilerService
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function profile(int $timeoutMsPerProbe = 2000): array
    {
        $metrics = [];
        $errors = [];

        $connectionProbe = $this->runTimedProbe(function (): array {
            $ok = $this->wpdb->check_connection(false);
            return ['connected' => (bool) $ok];
        }, $timeoutMsPerProbe);
        $metrics[] = $this->metric(
            'database.connection_latency_ms',
            $connectionProbe['elapsed_ms'],
            [
                'probe' => 'connection',
                'connected' => (bool) ($connectionProbe['payload']['connected'] ?? false),
                'timed_out' => $connectionProbe['timed_out'],
                'ok' => $connectionProbe['ok'],
            ]
        );
        if ($connectionProbe['error'] !== null) {
            $errors[] = $this->error('database.connection_latency_ms', $connectionProbe['error']);
        }

        $selectOneProbe = $this->runTimedProbe(function (): array {
            $value = $this->wpdb->get_var('SELECT 1');
            return ['value' => $value];
        }, $timeoutMsPerProbe);
        $metrics[] = $this->metric(
            'database.select_1_timing_ms',
            $selectOneProbe['elapsed_ms'],
            [
                'probe' => 'select_1',
                'value' => $selectOneProbe['payload']['value'] ?? null,
                'timed_out' => $selectOneProbe['timed_out'],
                'ok' => $selectOneProbe['ok'],
            ]
        );
        if ($selectOneProbe['error'] !== null) {
            $errors[] = $this->error('database.select_1_timing_ms', $selectOneProbe['error']);
        }

        $ticketsTable = $this->wpdb->prefix . 'pet_tickets';
        $customersTable = $this->wpdb->prefix . 'pet_customers';

        $indexedProbe = $this->runTimedProbe(function () use ($ticketsTable): array {
            $tableExists = $this->tableExists($ticketsTable);
            if (!$tableExists) {
                throw new \RuntimeException('missing_table:' . $ticketsTable);
            }
            $id = $this->wpdb->get_var("SELECT id FROM {$ticketsTable} WHERE id > 0 ORDER BY id DESC LIMIT 1");
            return ['sample_id' => $id];
        }, $timeoutMsPerProbe);
        $metrics[] = $this->metric(
            'database.indexed_query_timing_ms',
            $indexedProbe['elapsed_ms'],
            [
                'probe' => 'indexed_query',
                'table' => $ticketsTable,
                'sample_id' => $indexedProbe['payload']['sample_id'] ?? null,
                'timed_out' => $indexedProbe['timed_out'],
                'ok' => $indexedProbe['ok'],
            ]
        );
        if ($indexedProbe['error'] !== null) {
            $errors[] = $this->error('database.indexed_query_timing_ms', $indexedProbe['error']);
        }

        $joinProbe = $this->runTimedProbe(function () use ($ticketsTable, $customersTable): array {
            $ticketsExists = $this->tableExists($ticketsTable);
            $customersExists = $this->tableExists($customersTable);
            if (!$ticketsExists || !$customersExists) {
                throw new \RuntimeException('missing_table:' . (!$ticketsExists ? $ticketsTable : $customersTable));
            }
            $rows = $this->wpdb->get_results(
                "SELECT t.id FROM {$ticketsTable} t INNER JOIN {$customersTable} c ON c.id = t.customer_id ORDER BY t.id DESC LIMIT 5",
                ARRAY_A
            );
            return ['rows_returned' => \is_array($rows) ? \count($rows) : 0];
        }, $timeoutMsPerProbe);
        $metrics[] = $this->metric(
            'database.join_query_timing_ms',
            $joinProbe['elapsed_ms'],
            [
                'probe' => 'pet_style_join',
                'tickets_table' => $ticketsTable,
                'customers_table' => $customersTable,
                'rows_returned' => $joinProbe['payload']['rows_returned'] ?? 0,
                'timed_out' => $joinProbe['timed_out'],
                'ok' => $joinProbe['ok'],
            ]
        );
        if ($joinProbe['error'] !== null) {
            $errors[] = $this->error('database.join_query_timing_ms', $joinProbe['error']);
        }

        return [
            'metrics' => $metrics,
            'errors' => $errors,
        ];
    }

    private function runTimedProbe(callable $probe, int $timeoutMs): array
    {
        $start = \microtime(true);
        try {
            $payload = $probe();
            $elapsedMs = $this->elapsedMs($start);
            $timedOut = $elapsedMs > $timeoutMs;
            return [
                'ok' => !$timedOut,
                'elapsed_ms' => $elapsedMs,
                'timed_out' => $timedOut,
                'payload' => \is_array($payload) ? $payload : [],
                'error' => $timedOut ? 'probe_timeout_exceeded' : null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'elapsed_ms' => $this->elapsedMs($start),
                'timed_out' => false,
                'payload' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function tableExists(string $tableName): bool
    {
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
        return (string) $found === $tableName;
    }

    private function elapsedMs(float $startedAt): float
    {
        return \round((\microtime(true) - $startedAt) * 1000, 3);
    }

    private function metric(string $metricKey, $metricValue, array $context): array
    {
        return [
            'metric_key' => $metricKey,
            'metric_value' => $metricValue,
            'context' => $context,
        ];
    }

    private function error(string $metricKey, string $message): array
    {
        return [
            'metric_key' => $metricKey,
            'message' => $message,
        ];
    }
}

