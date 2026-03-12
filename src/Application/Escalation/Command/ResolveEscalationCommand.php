<?php

declare(strict_types=1);

namespace Pet\Application\Escalation\Command;

class ResolveEscalationCommand
{
    private int $escalationId;
    private int $actorId;
    private ?string $resolutionNote;

    public function __construct(int $escalationId, int $actorId, ?string $resolutionNote = null)
    {
        $this->escalationId = $escalationId;
        $this->actorId = $actorId;
        $this->resolutionNote = $resolutionNote;
    }

    public function escalationId(): int { return $this->escalationId; }
    public function actorId(): int { return $this->actorId; }
    public function resolutionNote(): ?string { return $this->resolutionNote; }
}
