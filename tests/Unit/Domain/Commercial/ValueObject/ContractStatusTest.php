<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial\ValueObject;

use Pet\Domain\Commercial\ValueObject\ContractStatus;
use PHPUnit\Framework\TestCase;

class ContractStatusTest extends TestCase
{
    // ── Factory methods ──

    public function testActiveFactory(): void
    {
        $this->assertSame('active', ContractStatus::active()->toString());
    }

    public function testCompletedFactory(): void
    {
        $this->assertSame('completed', ContractStatus::completed()->toString());
    }

    public function testTerminatedFactory(): void
    {
        $this->assertSame('terminated', ContractStatus::terminated()->toString());
    }

    // ── fromString ──

    /** @dataProvider validStatusesProvider */
    public function testFromStringValid(string $value): void
    {
        $this->assertSame($value, ContractStatus::fromString($value)->toString());
    }

    public function validStatusesProvider(): array
    {
        return [['active'], ['completed'], ['terminated']];
    }

    public function testFromStringInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ContractStatus::fromString('pending');
    }

    // ── Transitions ──

    public function testActiveCanTransitionToCompleted(): void
    {
        $this->assertTrue(ContractStatus::active()->canTransitionTo(ContractStatus::completed()));
    }

    public function testActiveCanTransitionToTerminated(): void
    {
        $this->assertTrue(ContractStatus::active()->canTransitionTo(ContractStatus::terminated()));
    }

    public function testActiveCannotTransitionToActive(): void
    {
        $this->assertFalse(ContractStatus::active()->canTransitionTo(ContractStatus::active()));
    }

    public function testCompletedCannotTransition(): void
    {
        $this->assertFalse(ContractStatus::completed()->canTransitionTo(ContractStatus::active()));
        $this->assertFalse(ContractStatus::completed()->canTransitionTo(ContractStatus::terminated()));
    }

    public function testTerminatedCannotTransition(): void
    {
        $this->assertFalse(ContractStatus::terminated()->canTransitionTo(ContractStatus::active()));
        $this->assertFalse(ContractStatus::terminated()->canTransitionTo(ContractStatus::completed()));
    }

    // ── isTerminal ──

    public function testActiveIsNotTerminal(): void
    {
        $this->assertFalse(ContractStatus::active()->isTerminal());
    }

    public function testCompletedIsTerminal(): void
    {
        $this->assertTrue(ContractStatus::completed()->isTerminal());
    }

    public function testTerminatedIsTerminal(): void
    {
        $this->assertTrue(ContractStatus::terminated()->isTerminal());
    }
}
