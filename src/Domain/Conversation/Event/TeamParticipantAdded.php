<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class TeamParticipantAdded extends AbstractConversationEvent
{
    private int $teamId;

    public function __construct(string $conversationUuid, int $teamId, int $actorId)
    {
        parent::__construct($conversationUuid, $actorId);
        $this->teamId = $teamId;
    }

    public function eventType(): string
    {
        return 'TeamParticipantAdded';
    }

    public function payload(): array
    {
        return [
            'team_id' => $this->teamId,
        ];
    }
}
