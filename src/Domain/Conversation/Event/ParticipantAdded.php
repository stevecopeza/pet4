<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class ParticipantAdded extends AbstractConversationEvent
{
    private int $userId;

    public function __construct(string $conversationUuid, int $userId, int $actorId)
    {
        parent::__construct($conversationUuid, $actorId);
        $this->userId = $userId;
    }

    public function eventType(): string
    {
        return 'ParticipantAdded';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
        ];
    }
}
