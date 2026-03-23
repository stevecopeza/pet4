<?php

declare(strict_types=1);

namespace Pet\Application\Performance\Dto;

final class PerformanceMetricSet
{
    /**
     * @var array<int, array{metric_key:string, metric_value:mixed, context:array<string,mixed>|null}>
     */
    private array $metrics = [];

    /**
     * @var array<int, array{metric_key:string, message:string, context:array<string,mixed>|null}>
     */
    private array $errors = [];

    /**
     * @param array<int, array{metric_key:string, metric_value:mixed, context?:array<string,mixed>|null}> $metrics
     * @param array<int, array{metric_key:string, message:string, context?:array<string,mixed>|null}> $errors
     */
    public function __construct(array $metrics = [], array $errors = [])
    {
        foreach ($metrics as $metric) {
            $this->addMetric(
                (string) ($metric['metric_key'] ?? ''),
                $metric['metric_value'] ?? null,
                isset($metric['context']) && \is_array($metric['context']) ? $metric['context'] : null
            );
        }
        foreach ($errors as $error) {
            $this->addError(
                (string) ($error['metric_key'] ?? ''),
                (string) ($error['message'] ?? ''),
                isset($error['context']) && \is_array($error['context']) ? $error['context'] : null
            );
        }
    }

    /**
     * @param array{metrics?:array<int, array{metric_key:string, metric_value:mixed, context?:array<string,mixed>|null}>, errors?:array<int, array{metric_key:string, message:string, context?:array<string,mixed>|null}>} $probeResult
     */
    public static function fromProbeResult(array $probeResult): self
    {
        $metrics = isset($probeResult['metrics']) && \is_array($probeResult['metrics']) ? $probeResult['metrics'] : [];
        $errors = isset($probeResult['errors']) && \is_array($probeResult['errors']) ? $probeResult['errors'] : [];
        return new self($metrics, $errors);
    }

    /**
     * @param array<string,mixed>|null $context
     */
    public function addMetric(string $metricKey, $metricValue, ?array $context = null): void
    {
        if ($metricKey === '') {
            return;
        }

        $this->metrics[] = [
            'metric_key' => $metricKey,
            'metric_value' => $metricValue,
            'context' => $context,
        ];
    }

    /**
     * @param array<string,mixed>|null $context
     */
    public function addError(string $metricKey, string $message, ?array $context = null): void
    {
        if ($metricKey === '') {
            return;
        }

        $this->errors[] = [
            'metric_key' => $metricKey,
            'message' => $message,
            'context' => $context,
        ];
    }

    public function merge(self $other): self
    {
        $merged = new self($this->metrics, $this->errors);
        foreach ($other->metrics() as $metric) {
            $merged->addMetric($metric['metric_key'], $metric['metric_value'], $metric['context']);
        }
        foreach ($other->errors() as $error) {
            $merged->addError($error['metric_key'], $error['message'], $error['context']);
        }
        return $merged;
    }

    /**
     * @return array<int, array{metric_key:string, metric_value:mixed, context:array<string,mixed>|null}>
     */
    public function metrics(): array
    {
        return $this->metrics;
    }

    /**
     * @return array<int, array{metric_key:string, message:string, context:array<string,mixed>|null}>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

