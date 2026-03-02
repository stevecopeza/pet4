<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class ConversationReopened extends AbstractConversationEvent
{
    public function eventType(): string
    {
        return 'ConversationReopened';
    }

    public function payload(): array
    {
        return [];
    }
}
