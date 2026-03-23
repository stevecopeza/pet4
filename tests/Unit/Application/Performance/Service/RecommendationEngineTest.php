<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Performance\Service;

use Pet\Application\Performance\Service\RecommendationEngine;
use PHPUnit\Framework\TestCase;

final class RecommendationEngineTest extends TestCase
{
    public function testMapsNoObjectCacheRecommendation(): void
    {
        $engine = new RecommendationEngine();

        $recommendations = $engine->recommend([
            ['metric_key' => 'cache.backend', 'metric_value' => 'none', 'context' => null],
            ['metric_key' => 'cache.available', 'metric_value' => false, 'context' => null],
        ]);

        self::assertNotEmpty($recommendations);
        self::assertSame('no_object_cache', $recommendations[0]['issue_key'] ?? null);
        self::assertSame('CRITICAL', $recommendations[0]['severity'] ?? null);
    }

    public function testMapsHighDatabaseLatencyRecommendation(): void
    {
        $engine = new RecommendationEngine();

        $recommendations = $engine->recommend([
            ['metric_key' => 'database.connection_latency_ms', 'metric_value' => 275.4, 'context' => null],
            ['metric_key' => 'cache.backend', 'metric_value' => 'redis', 'context' => null],
            ['metric_key' => 'cache.available', 'metric_value' => true, 'context' => null],
        ]);

        $issueKeys = \array_column($recommendations, 'issue_key');
        self::assertContains('high_db_latency', $issueKeys);
    }

    public function testMapsLowCacheHitRateOnlyWhenStatsAvailable(): void
    {
        $engine = new RecommendationEngine();

        $recommendations = $engine->recommend([
            ['metric_key' => 'cache.stats_available', 'metric_value' => true, 'context' => null],
            ['metric_key' => 'cache.hit_rate', 'metric_value' => 65.0, 'context' => null],
            ['metric_key' => 'cache.backend', 'metric_value' => 'redis', 'context' => null],
            ['metric_key' => 'cache.available', 'metric_value' => true, 'context' => null],
        ]);

        $issueKeys = \array_column($recommendations, 'issue_key');
        self::assertContains('low_cache_hit_rate', $issueKeys);
    }
}

