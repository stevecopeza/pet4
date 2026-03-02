<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Delivery\ValueObject;

use Pet\Domain\Delivery\ValueObject\ProjectState;
use PHPUnit\Framework\TestCase;

class ProjectStateTest extends TestCase
{
    // ── Factory methods ──

    /** @dataProvider factoryProvider */
    public function testFactoryMethods(string $method, string $expected): void
    {
        $this->assertSame($expected, ProjectState::$method()->toString());
    }

    public function factoryProvider(): array
    {
        return [
            ['planned', 'planned'],
            ['active', 'active'],
            ['onHold', 'on_hold'],
            ['completed', 'completed'],
            ['cancelled', 'cancelled'],
        ];
    }

    // ── fromString ──

    /** @dataProvider validStatesProvider */
    public function testFromStringValid(string $value): void
    {
        $this->assertSame($value, ProjectState::fromString($value)->toString());
    }

    public function validStatesProvider(): array
    {
        return [['planned'], ['active'], ['on_hold'], ['completed'], ['cancelled']];
    }

    public function testFromStringInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProjectState::fromString('unknown');
    }

    // ── isTerminal ──

    /** @dataProvider nonTerminalProvider */
    public function testNonTerminalStates(string $state): void
    {
        $this->assertFalse(ProjectState::fromString($state)->isTerminal());
    }

    public function nonTerminalProvider(): array
    {
        return [['planned'], ['active'], ['on_hold']];
    }

    /** @dataProvider terminalProvider */
    public function testTerminalStates(string $state): void
    {
        $this->assertTrue(ProjectState::fromString($state)->isTerminal());
    }

    public function terminalProvider(): array
    {
        return [['completed'], ['cancelled']];
    }
}
