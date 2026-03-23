<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Performance;

class NetworkProbeService
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function probe(float $httpTimeoutSeconds = 2.0, int $dbPingTimeoutMs = 1000): array
    {
        $metrics = [];
        $errors = [];

        $dbPing = $this->runTimedProbe(function (): array {
            $value = $this->wpdb->get_var('SELECT 1');
            return ['value' => $value];
        }, $dbPingTimeoutMs);
        $metrics[] = $this->metric(
            'network.db_ping_latency_ms',
            $dbPing['elapsed_ms'],
            [
                'ok' => $dbPing['ok'],
                'value' => $dbPing['payload']['value'] ?? null,
                'timed_out' => $dbPing['timed_out'],
            ]
        );
        if ($dbPing['error'] !== null) {
            $errors[] = $this->error('network.db_ping_latency_ms', $dbPing['error']);
        }

        $loopbackUrl = \function_exists('rest_url') ? \rest_url() : null;
        $loopback = $this->timedHttpGet($loopbackUrl, $httpTimeoutSeconds);
        $metrics[] = $this->metric(
            'network.http_loopback_latency_ms',
            $loopback['elapsed_ms'],
            [
                'ok' => $loopback['ok'],
                'url' => $loopbackUrl,
                'status_code' => $loopback['status_code'],
                'timed_out' => $loopback['timed_out'],
                'latency_scope' => 'server_internal_loopback',
                'read_only_endpoint' => true,
                'benchmark_trigger_endpoint' => false,
            ]
        );
        if ($loopback['error'] !== null) {
            $errors[] = $this->error('network.http_loopback_latency_ms', $loopback['error']);
        }

        $publicUrl = \function_exists('home_url') ? \home_url('/') : null;
        $public = $this->timedHttpGet($publicUrl, $httpTimeoutSeconds);
        $metrics[] = $this->metric(
            'network.http_public_latency_ms',
            $public['elapsed_ms'],
            [
                'ok' => $public['ok'],
                'url' => $publicUrl,
                'status_code' => $public['status_code'],
                'timed_out' => $public['timed_out'],
                'latency_scope' => 'server_initiated_public_path',
                'not_browser_client_latency' => true,
            ]
        );
        if ($public['error'] !== null) {
            $errors[] = $this->error('network.http_public_latency_ms', $public['error']);
        }

        return [
            'metrics' => $metrics,
            'errors' => $errors,
        ];
    }

    private function timedHttpGet(?string $url, float $timeoutSeconds): array
    {
        if ($url === null || $url === '' || !\function_exists('wp_remote_get')) {
            return [
                'ok' => false,
                'elapsed_ms' => 0.0,
                'payload' => [],
                'status_code' => null,
                'timed_out' => false,
                'error' => 'http_probe_unavailable',
            ];
        }

        $start = \microtime(true);
        try {
            $response = \wp_remote_get($url, [
                'timeout' => $timeoutSeconds,
                'redirection' => 3,
                'sslverify' => false,
            ]);
            $elapsed = $this->elapsedMs($start);
            if (\is_wp_error($response)) {
                $errorMessage = $response->get_error_message();
                $timedOut = $this->isTimeoutErrorMessage($errorMessage);
                return [
                    'ok' => false,
                    'elapsed_ms' => $elapsed,
                    'payload' => [],
                    'status_code' => null,
                    'timed_out' => $timedOut,
                    'error' => $timedOut ? 'probe_timeout_exceeded' : $errorMessage,
                ];
            }
            $statusCode = \wp_remote_retrieve_response_code($response);
            return [
                'ok' => true,
                'elapsed_ms' => $elapsed,
                'payload' => [],
                'status_code' => $statusCode,
                'timed_out' => false,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $timedOut = $this->isTimeoutErrorMessage($errorMessage);
            return [
                'ok' => false,
                'elapsed_ms' => $this->elapsedMs($start),
                'payload' => [],
                'status_code' => null,
                'timed_out' => $timedOut,
                'error' => $timedOut ? 'probe_timeout_exceeded' : $errorMessage,
            ];
        }
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
                'payload' => \is_array($payload) ? $payload : [],
                'timed_out' => $timedOut,
                'error' => $timedOut ? 'probe_timeout_exceeded' : null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'elapsed_ms' => $this->elapsedMs($start),
                'payload' => [],
                'timed_out' => false,
                'error' => $e->getMessage(),
            ];
        }
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

    private function isTimeoutErrorMessage(string $message): bool
    {
        $normalized = \strtolower($message);
        return \str_contains($normalized, 'timed out')
            || \str_contains($normalized, 'timeout')
            || \str_contains($normalized, 'operation timed out');
    }
}

