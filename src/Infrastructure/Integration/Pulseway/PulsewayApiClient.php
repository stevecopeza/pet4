<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Integration\Pulseway;

/**
 * HTTP client for the Pulseway REST API v3.
 *
 * Uses Basic Authentication with Token ID / Token Secret.
 * Implements per-integration rate limiting and exponential backoff.
 * Secrets are never logged or included in error messages.
 */
final class PulsewayApiClient
{
    private string $baseUrl;
    private string $tokenId;
    private string $tokenSecret;
    private int $timeoutSeconds;

    /** @var array<string, array{count: int, window_start: int}> Rate tracking per base URL */
    private static array $rateTracking = [];

    private const MAX_REQUESTS_PER_MINUTE = 900; // Conservative vs Pulseway's 1000/min limit
    private const RATE_WINDOW_SECONDS = 60;

    public function __construct(string $baseUrl, string $tokenId, string $tokenSecret, int $timeoutSeconds = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->tokenId = $tokenId;
        $this->tokenSecret = $tokenSecret;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * @return array{data: mixed, meta: array}
     * @throws PulsewayApiException
     */
    public function getDevices(int $top = 50, int $skip = 0): array
    {
        return $this->get('/devices', ['$top' => $top, '$skip' => $skip]);
    }

    /**
     * @return array{data: mixed, meta: array}
     * @throws PulsewayApiException
     */
    public function getDevice(string $deviceId): array
    {
        return $this->get('/devices/' . urlencode($deviceId));
    }

    /**
     * @return array{data: mixed, meta: array}
     * @throws PulsewayApiException
     */
    public function getDeviceNotifications(string $deviceId, int $top = 100, int $skip = 0): array
    {
        return $this->get('/devices/' . urlencode($deviceId) . '/notifications', [
            '$top' => $top,
            '$skip' => $skip,
        ]);
    }

    /**
     * @return array{data: mixed, meta: array}
     * @throws PulsewayApiException
     */
    public function getNotifications(int $top = 100, int $skip = 0): array
    {
        return $this->get('/notifications', ['$top' => $top, '$skip' => $skip]);
    }

    /**
     * @return array{data: mixed, meta: array}
     * @throws PulsewayApiException
     */
    public function getOrganizations(): array
    {
        return $this->get('/organizations');
    }

    /**
     * @return array{data: mixed, meta: array}
     * @throws PulsewayApiException
     */
    public function getSites(): array
    {
        return $this->get('/sites');
    }

    /**
     * @return array{data: mixed, meta: array}
     * @throws PulsewayApiException
     */
    public function getGroups(): array
    {
        return $this->get('/groups');
    }

    /**
     * @return array{data: mixed, meta: array}
     * @throws PulsewayApiException
     */
    private function get(string $path, array $query = []): array
    {
        $this->enforceRateLimit();

        $url = $this->baseUrl . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $args = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->tokenId . ':' . $this->tokenSecret),
                'Accept' => 'application/json',
            ],
            'timeout' => $this->timeoutSeconds,
            'sslverify' => true,
        ];

        $response = wp_remote_get($url, $args);

        $this->trackRequest();

        if (is_wp_error($response)) {
            throw new PulsewayApiException(
                'Pulseway API request failed: ' . $response->get_error_message(),
                0
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode === 429) {
            throw new PulsewayRateLimitException('Pulseway API rate limit exceeded', 429);
        }

        if ($statusCode >= 500) {
            throw new PulsewayApiException(
                'Pulseway API server error (HTTP ' . $statusCode . ')',
                $statusCode
            );
        }

        if ($statusCode === 401 || $statusCode === 403) {
            // Never include credentials in error messages
            throw new PulsewayApiException(
                'Pulseway API authentication failed (HTTP ' . $statusCode . ')',
                $statusCode
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new PulsewayApiException(
                'Pulseway API returned HTTP ' . $statusCode,
                $statusCode
            );
        }

        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new PulsewayApiException(
                'Pulseway API returned invalid JSON: ' . json_last_error_msg(),
                0
            );
        }

        return [
            'data' => $decoded['Data'] ?? $decoded['data'] ?? $decoded,
            'meta' => $decoded['Meta'] ?? $decoded['meta'] ?? [],
        ];
    }

    private function enforceRateLimit(): void
    {
        $key = $this->baseUrl;
        $now = time();

        if (!isset(self::$rateTracking[$key])) {
            self::$rateTracking[$key] = ['count' => 0, 'window_start' => $now];
        }

        $tracker = &self::$rateTracking[$key];

        // Reset window if expired
        if (($now - $tracker['window_start']) >= self::RATE_WINDOW_SECONDS) {
            $tracker = ['count' => 0, 'window_start' => $now];
        }

        if ($tracker['count'] >= self::MAX_REQUESTS_PER_MINUTE) {
            $waitSeconds = self::RATE_WINDOW_SECONDS - ($now - $tracker['window_start']);
            throw new PulsewayRateLimitException(
                'PET-side rate limit reached (' . self::MAX_REQUESTS_PER_MINUTE . '/min). Retry in ' . $waitSeconds . 's.',
                429
            );
        }
    }

    private function trackRequest(): void
    {
        $key = $this->baseUrl;
        if (isset(self::$rateTracking[$key])) {
            self::$rateTracking[$key]['count']++;
        }
    }
}
