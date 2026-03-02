<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

class RemoveParticipantCommand
{
    private string $conversationUuid;
    private string $participantType; // 'user', 'contact', 'team'
    private int $participantId;
    private int $actorId;

    public function __construct(
        string $conversationUuid,
        string $participantType,
        int $participantId,
        int $actorId
    ) {
        $this->conversationUuid = $conversationUuid;
        $this->participantType = $participantType;
        $this->participantId = $participantId;
        $this->actorId = $actorId;
    }

    public function conversationUuid(): string { return $this->conversationUuid; }
    public function participantType(): string { return $this->participantType; }
    public function participantId(): int { return $this->participantId; }
    public function actorId(): int { return $this->actorId; }
}
