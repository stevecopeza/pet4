<?php

declare(strict_types=1);

namespace Pet\Application\Escalation\Listener;

use Pet\Application\Escalation\Command\TriggerEscalationCommand;
use Pet\Application\Escalation\Command\TriggerEscalationHandler;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;

/**
 * Bridges the existing SLA-domain EscalationTriggeredEvent to the new Escalation aggregate.
 * When an SLA breach fires, this listener creates a proper Escalation record
 * without modifying any existing SLA code.
 */
class SlaEscalationBridgeListener
{
    private TriggerEscalationHandler $handler;
    private FeatureFlagService $featureFlags;

    public function __construct(
        TriggerEscalationHandler $handler,
        FeatureFlagService $featureFlags
    ) {
        $this->handler = $handler;
        $this->featureFlags = $featureFlags;
    }

    public function __invoke(EscalationTriggeredEvent $event): void
    {
        if (!$this->featureFlags->isEscalationEngineEnabled()) {
            return;
        }

        $severity = $this->mapStageToSeverity($event->stage());

        $command = new TriggerEscalationCommand(
            'ticket',
            $event->ticketId(),
            $severity,
            sprintf('SLA breach – escalation stage %d', $event->stage()),
            null,
            [
                'origin' => 'sla_breach',
                'stage' => $event->stage(),
                'tier_priority' => $event->tierPriority(),
            ],
            'SLA Breach'
        );

        $this->handler->handle($command);
    }

    private function mapStageToSeverity(int $stage): string
    {
        return match (true) {
            $stage >= 3 => Escalation::SEVERITY_CRITICAL,
            $stage === 2 => Escalation::SEVERITY_HIGH,
            default => Escalation::SEVERITY_MEDIUM,
        };
    }
}
