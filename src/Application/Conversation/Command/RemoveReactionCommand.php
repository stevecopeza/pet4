<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

class RemoveReactionCommand
{
    private string $conversationUuid;
    private int $messageId;
    private string $reactionType;
    private int $actorId;

    public function __construct(
        string $conversationUuid,
        int $messageId,
        string $reactionType,
        int $actorId
    ) {
        $this->conversationUuid = $conversationUuid;
        $this->messageId = $messageId;
        $this->reactionType = $reactionType;
        $this->actorId = $actorId;
    }

    public function conversationUuid(): string { return $this->conversationUuid; }
    public function messageId(): int { return $this->messageId; }
    public function reactionType(): string { return $this->reactionType; }
    public function actorId(): int { return $this->actorId; }
}
