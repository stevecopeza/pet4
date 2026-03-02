<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class ContactParticipantAdded extends AbstractConversationEvent
{
    private int $contactId;

    public function __construct(string $conversationUuid, int $contactId, int $actorId)
    {
        parent::__construct($conversationUuid, $actorId);
        $this->contactId = $contactId;
    }

    public function eventType(): string
    {
        return 'ContactParticipantAdded';
    }

    public function payload(): array
    {
        return [
            'contact_id' => $this->contactId,
        ];
    }
}
