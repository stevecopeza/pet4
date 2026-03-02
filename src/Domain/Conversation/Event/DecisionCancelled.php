<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class DecisionCancelled extends AbstractDecisionEvent
{
    private string $reason;

    public function __construct(string $decisionUuid, string $reason, int $actorId)
    {
        parent::__construct($decisionUuid, $actorId);
        $this->reason = $reason;
    }

    public function eventType(): string
    {
        return 'DecisionCancelled';
    }

    public function payload(): array
    {
        return [
            'reason' => $this->reason,
        ];
    }
}
