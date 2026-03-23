<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Performance;

use Pet\Infrastructure\Performance\PetWorkloadProfiler;
use PHPUnit\Framework\TestCase;

final class PetWorkloadProfilerTest extends TestCase
{
    public function testBeginReturnsNullWhenInactive(): void
    {
        $wpdb = $this->fakeWpdb(5);
        $profiler = new PetWorkloadProfiler($wpdb);

        self::assertNull($profiler->begin(false));
    }

    public function testEndReturnsAggregatedMetricsWhenActive(): void
    {
        $wpdb = $this->fakeWpdb(10);
        $profiler = new PetWorkloadProfiler($wpdb);

        $token = $profiler->begin(true);
        self::assertIsArray($token);

        $wpdb->num_queries = 14;
        $result = $profiler->end('dashboard', $token);

        self::assertIsArray($result);
        self::assertSame('dashboard', $result['workload_key']);
        self::assertSame(4, $result['query_count']);
        self::assertIsFloat($result['execution_time_ms']);
        self::assertGreaterThanOrEqual(0.0, $result['execution_time_ms']);
    }

    public function testEndClampsNegativeQueryDeltaToZero(): void
    {
        $wpdb = $this->fakeWpdb(10);
        $profiler = new PetWorkloadProfiler($wpdb);

        $token = $profiler->begin(true);
        self::assertIsArray($token);

        $wpdb->num_queries = 8;
        $result = $profiler->end('ticket.list', $token);

        self::assertIsArray($result);
        self::assertSame(0, $result['query_count']);
    }

    /**
     * @return \wpdb&object{num_queries:int,queries:array<int,mixed>}
     */
    private function fakeWpdb(int $numQueries): \wpdb
    {
        return new class($numQueries) extends \wpdb {
            public int $num_queries;

            /** @var array<int,mixed> */
            public array $queries = [];

            public function __construct(int $numQueries)
            {
                $this->num_queries = $numQueries;
            }
        };
    }
}

