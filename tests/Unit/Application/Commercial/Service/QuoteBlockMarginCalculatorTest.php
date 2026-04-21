<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Service;

use Pet\Application\Commercial\Service\QuoteBlockMarginCalculator;
use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use PHPUnit\Framework\TestCase;

class QuoteBlockMarginCalculatorTest extends TestCase
{
    private QuoteBlockMarginCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new QuoteBlockMarginCalculator();
    }

    public function testCalculatesFlatBlockMarginFromPersistedSellAndCost(): void
    {
        $payload = [
            'totalValue' => 200.0,
            'totalCost' => 120.0,
        ];

        $result = $this->calculator->calculate(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $payload);

        $this->assertSame(200.0, $result['lineSellValue']);
        $this->assertSame(120.0, $result['lineCostValue']);
        $this->assertSame(80.0, $result['marginAmount']);
        $this->assertSame(40.0, $result['marginPercentage']);
        $this->assertTrue($result['hasMarginData']);
    }

    public function testReturnsNullMarginWhenCostBasisIsMissing(): void
    {
        $payload = [
            'totalValue' => 200.0,
        ];

        $result = $this->calculator->calculate(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $payload);

        $this->assertSame(200.0, $result['lineSellValue']);
        $this->assertNull($result['lineCostValue']);
        $this->assertNull($result['marginAmount']);
        $this->assertNull($result['marginPercentage']);
        $this->assertFalse($result['hasMarginData']);
    }

    public function testReturnsNullPercentageWhenSellValueIsZero(): void
    {
        $payload = [
            'totalValue' => 0.0,
            'totalCost' => 50.0,
        ];

        $result = $this->calculator->calculate(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $payload);

        $this->assertSame(-50.0, $result['marginAmount']);
        $this->assertNull($result['marginPercentage']);
        $this->assertTrue($result['hasMarginData']);
    }

    public function testCalculatesRecurringMarginFromPersistedPerPeriodSnapshots(): void
    {
        $payload = [
            'sellPricePerPeriod' => 100.0,
            'internalCostPerPeriod' => 60.0,
            'cadence' => 'monthly',
            'termMonths' => 12,
        ];

        $result = $this->calculator->calculate(QuoteBlock::TYPE_REPEAT_SERVICE, $payload);

        $this->assertSame(1200.0, $result['lineSellValue']);
        $this->assertSame(720.0, $result['lineCostValue']);
        $this->assertSame(480.0, $result['marginAmount']);
        $this->assertSame(40.0, $result['marginPercentage']);
        $this->assertTrue($result['hasMarginData']);
    }

    public function testCalculatesProjectAndNestedMarginsFromPersistedSnapshotFields(): void
    {
        $payload = [
            'phases' => [
                [
                    'name' => 'Phase A',
                    'units' => [
                        ['description' => 'Unit A', 'totalValue' => 100.0, 'totalCost' => 70.0],
                        ['description' => 'Unit B', 'totalValue' => 200.0, 'totalCost' => 100.0],
                    ],
                ],
            ],
            'totalValue' => 300.0,
        ];

        $result = $this->calculator->calculate(QuoteBlock::TYPE_ONCE_OFF_PROJECT, $payload);

        $this->assertSame(300.0, $result['lineSellValue']);
        $this->assertSame(170.0, $result['lineCostValue']);
        $this->assertSame(130.0, $result['marginAmount']);
        $this->assertTrue($result['hasMarginData']);

        $enrichedPayload = $result['payload'];
        $this->assertSame(30.0, $enrichedPayload['phases'][0]['units'][0]['marginAmount']);
        $this->assertSame(100.0, $enrichedPayload['phases'][0]['units'][1]['marginAmount']);
        $this->assertSame(130.0, $enrichedPayload['phases'][0]['marginAmount']);
        $this->assertEqualsWithDelta(43.333333333333336, $enrichedPayload['phases'][0]['marginPercentage'], 0.000001);
    }

    public function testProjectMarginRemainsUnavailableWhenAnyChildCostIsMissing(): void
    {
        $payload = [
            'phases' => [
                [
                    'name' => 'Phase A',
                    'units' => [
                        ['description' => 'Unit A', 'totalValue' => 100.0, 'totalCost' => 70.0],
                        ['description' => 'Unit B', 'totalValue' => 200.0],
                    ],
                ],
            ],
            'totalValue' => 300.0,
        ];

        $result = $this->calculator->calculate(QuoteBlock::TYPE_ONCE_OFF_PROJECT, $payload);

        $this->assertSame(300.0, $result['lineSellValue']);
        $this->assertNull($result['lineCostValue']);
        $this->assertNull($result['marginAmount']);
        $this->assertFalse($result['hasMarginData']);
    }

    public function testProjectParentMarginNeverFallsBackToParentTotalCostWhenChildCostIsMissing(): void
    {
        $payload = [
            'totalValue' => 300.0,
            'totalCost' => 999.0,
            'phases' => [
                [
                    'name' => 'Phase A',
                    'units' => [
                        ['description' => 'Unit A', 'totalValue' => 100.0, 'totalCost' => 70.0],
                        ['description' => 'Unit B', 'totalValue' => 200.0],
                    ],
                ],
            ],
        ];

        $result = $this->calculator->calculate(QuoteBlock::TYPE_ONCE_OFF_PROJECT, $payload);

        $this->assertSame(300.0, $result['lineSellValue']);
        $this->assertNull($result['lineCostValue']);
        $this->assertNull($result['marginAmount']);
        $this->assertNull($result['marginPercentage']);
        $this->assertFalse($result['hasMarginData']);
    }

    public function testProjectParentMarginUsesStrictChildCostAggregationWhenComplete(): void
    {
        $payload = [
            'totalValue' => 300.0,
            'totalCost' => 999.0,
            'phases' => [
                [
                    'name' => 'Phase A',
                    'units' => [
                        ['description' => 'Unit A', 'totalValue' => 100.0, 'totalCost' => 70.0],
                        ['description' => 'Unit B', 'totalValue' => 200.0, 'totalCost' => 110.0],
                    ],
                ],
            ],
        ];

        $result = $this->calculator->calculate(QuoteBlock::TYPE_ONCE_OFF_PROJECT, $payload);

        $this->assertSame(180.0, $result['lineCostValue']);
        $this->assertSame(120.0, $result['marginAmount']);
        $this->assertTrue($result['hasMarginData']);
    }
}

