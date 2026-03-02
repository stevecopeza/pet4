<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class DecisionFinalized extends AbstractDecisionEvent
{
    private string $outcome; // 'approved', 'rejected'

    public function __construct(string $decisionUuid, string $outcome, int $actorId)
    {
        parent::__construct($decisionUuid, $actorId);
        $this->outcome = $outcome;
    }

    public function eventType(): string
    {
        return 'DecisionFinalized';
    }

    public function payload(): array
    {
        return [
            'outcome' => $this->outcome,
        ];
    }
}
