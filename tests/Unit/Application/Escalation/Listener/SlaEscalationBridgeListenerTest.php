<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Escalation\Listener;

use Pet\Application\Escalation\Command\TriggerEscalationHandler;
use Pet\Application\Escalation\Listener\SlaEscalationBridgeListener;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;
use PHPUnit\Framework\TestCase;

class SlaEscalationBridgeListenerTest extends TestCase
{
    // ── E / G. Feature flag off prevents escalation creation ──

    public function testBridgeDoesNotFireWhenFlagOff(): void
    {
        $flags = $this->createMock(FeatureFlagService::class);
        $flags->method('isEscalationEngineEnabled')->willReturn(false);

        $handler = $this->createMock(TriggerEscalationHandler::class);
        $handler->expects($this->never())->method('handle');

        $listener = new SlaEscalationBridgeListener($handler, $flags);
        $listener(new EscalationTriggeredEvent(42, 1, 3));
    }

    public function testBridgeFiresWhenFlagOn(): void
    {
        $flags = $this->createMock(FeatureFlagService::class);
        $flags->method('isEscalationEngineEnabled')->willReturn(true);

        $handler = $this->createMock(TriggerEscalationHandler::class);
        $handler->expects($this->once())->method('handle')->willReturn(1);

        $listener = new SlaEscalationBridgeListener($handler, $flags);
        $listener(new EscalationTriggeredEvent(42, 1, 3));
    }

    public function testBridgeMapsStageToCorrectSeverity(): void
    {
        $flags = $this->createMock(FeatureFlagService::class);
        $flags->method('isEscalationEngineEnabled')->willReturn(true);

        $capturedCommands = [];
        $handler = $this->createMock(TriggerEscalationHandler::class);
        $handler->method('handle')->willReturnCallback(function ($cmd) use (&$capturedCommands) {
            $capturedCommands[] = $cmd;
            return 1;
        });

        $listener = new SlaEscalationBridgeListener($handler, $flags);

        // Stage 1 → MEDIUM
        $listener(new EscalationTriggeredEvent(42, 1, null));
        $this->assertSame('MEDIUM', $capturedCommands[0]->severity());

        // Stage 2 → HIGH
        $listener(new EscalationTriggeredEvent(42, 2, null));
        $this->assertSame('HIGH', $capturedCommands[1]->severity());

        // Stage 3+ → CRITICAL
        $listener(new EscalationTriggeredEvent(42, 3, null));
        $this->assertSame('CRITICAL', $capturedCommands[2]->severity());
    }
}
