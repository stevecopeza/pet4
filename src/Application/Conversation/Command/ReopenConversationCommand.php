<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

class ReopenConversationCommand
{
    private string $conversationUuid;
    private int $actorId;

    public function __construct(string $conversationUuid, int $actorId)
    {
        $this->conversationUuid = $conversationUuid;
        $this->actorId = $actorId;
    }

    public function conversationUuid(): string { return $this->conversationUuid; }
    public function actorId(): int { return $this->actorId; }
}
