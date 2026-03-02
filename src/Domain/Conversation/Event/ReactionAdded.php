<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class ReactionAdded extends AbstractConversationEvent
{
    private int $messageId;
    private string $reactionType;

    public function __construct(
        string $conversationUuid,
        int $messageId,
        string $reactionType,
        int $actorId
    ) {
        parent::__construct($conversationUuid, $actorId);
        $this->messageId = $messageId;
        $this->reactionType = $reactionType;
    }

    public function eventType(): string
    {
        return 'ReactionAdded';
    }

    public function payload(): array
    {
        return [
            'message_id' => $this->messageId,
            'reaction_type' => $this->reactionType,
        ];
    }
    
    public function messageId(): int { return $this->messageId; }
    public function reactionType(): string { return $this->reactionType; }
}
