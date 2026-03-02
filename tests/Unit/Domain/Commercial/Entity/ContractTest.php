<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial\Entity;

use Pet\Domain\Commercial\Entity\Contract;
use Pet\Domain\Commercial\ValueObject\ContractStatus;
use PHPUnit\Framework\TestCase;

class ContractTest extends TestCase
{
    private function makeContract(string $status = 'active'): Contract
    {
        return new Contract(
            quoteId: 1,
            customerId: 10,
            status: ContractStatus::fromString($status),
            totalValue: 15000.0,
            currency: 'ZAR',
            startDate: new \DateTimeImmutable('2026-01-01')
        );
    }

    // ── Construction ──

    public function testConstructionSetsFields(): void
    {
        $contract = $this->makeContract();
        $this->assertSame(1, $contract->quoteId());
        $this->assertSame(10, $contract->customerId());
        $this->assertSame('active', $contract->status()->toString());
        $this->assertSame(15000.0, $contract->totalValue());
        $this->assertSame('ZAR', $contract->currency());
        $this->assertNull($contract->endDate());
        $this->assertNull($contract->id());
    }

    // ── complete() ──

    public function testCompleteFromActive(): void
    {
        $contract = $this->makeContract();
        $end = new \DateTimeImmutable('2026-06-30');
        $contract->complete($end);

        $this->assertSame('completed', $contract->status()->toString());
        $this->assertEquals($end, $contract->endDate());
        $this->assertNotNull($contract->updatedAt());
    }

    public function testCompleteFromCompletedThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid state transition');

        $contract = $this->makeContract();
        $contract->complete(new \DateTimeImmutable());
        $contract->complete(new \DateTimeImmutable()); // second call
    }

    public function testCompleteFromTerminatedThrows(): void
    {
        $this->expectException(\DomainException::class);

        $contract = $this->makeContract();
        $contract->terminate(new \DateTimeImmutable());
        $contract->complete(new \DateTimeImmutable());
    }

    // ── terminate() ──

    public function testTerminateFromActive(): void
    {
        $contract = $this->makeContract();
        $end = new \DateTimeImmutable('2026-03-15');
        $contract->terminate($end);

        $this->assertSame('terminated', $contract->status()->toString());
        $this->assertEquals($end, $contract->endDate());
    }

    public function testTerminateFromTerminatedThrows(): void
    {
        $this->expectException(\DomainException::class);

        $contract = $this->makeContract();
        $contract->terminate(new \DateTimeImmutable());
        $contract->terminate(new \DateTimeImmutable());
    }

    public function testTerminateFromCompletedThrows(): void
    {
        $this->expectException(\DomainException::class);

        $contract = $this->makeContract();
        $contract->complete(new \DateTimeImmutable());
        $contract->terminate(new \DateTimeImmutable());
    }
}
