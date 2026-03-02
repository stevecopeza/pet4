<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class ConversationResolved extends AbstractConversationEvent
{
    public function eventType(): string
    {
        return 'ConversationResolved';
    }

    public function payload(): array
    {
        return [];
    }
}
