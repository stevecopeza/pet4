<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

abstract class AbstractDecisionEvent implements DecisionEvent
{
    protected string $decisionUuid;
    protected int $actorId;
    protected \DateTimeImmutable $occurredAt;

    public function __construct(string $decisionUuid, int $actorId)
    {
        $this->decisionUuid = $decisionUuid;
        $this->actorId = $actorId;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function decisionUuid(): string
    {
        return $this->decisionUuid;
    }

    public function actorId(): int
    {
        return $this->actorId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
