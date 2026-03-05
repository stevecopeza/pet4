<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial\Entity;

use Pet\Domain\Commercial\Entity\Component\QuoteTask;
use PHPUnit\Framework\TestCase;

class QuoteTaskExtendedTest extends TestCase
{
    public function testServiceTypeIdAndRateCardIdDefault(): void
    {
        $task = new QuoteTask('Task A', 8.0, 1, 60.0, 120.0);
        $this->assertNull($task->serviceTypeId());
        $this->assertNull($task->rateCardId());
    }

    public function testServiceTypeIdAndRateCardIdSet(): void
    {
        $task = new QuoteTask('Task B', 4.0, 1, 60.0, 120.0, 'Desc', null, 5, 10);
        $this->assertSame(5, $task->serviceTypeId());
        $this->assertSame(10, $task->rateCardId());
    }

    public function testSellValueCalculation(): void
    {
        $task = new QuoteTask('Task C', 10.0, 1, 50.0, 100.0);
        $this->assertSame(1000.0, $task->sellValue());
    }

    public function testInternalCostCalculation(): void
    {
        $task = new QuoteTask('Task D', 10.0, 1, 50.0, 100.0);
        $this->assertSame(500.0, $task->internalCost());
    }
}
