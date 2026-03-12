<?php

declare(strict_types=1);

namespace Pet\Application\Escalation\Command;

class AcknowledgeEscalationCommand
{
    private int $escalationId;
    private int $actorId;

    public function __construct(int $escalationId, int $actorId)
    {
        $this->escalationId = $escalationId;
        $this->actorId = $actorId;
    }

    public function escalationId(): int { return $this->escalationId; }
    public function actorId(): int { return $this->actorId; }
}
