<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

abstract class AbstractConversationEvent implements ConversationEvent
{
    protected string $conversationUuid;
    protected int $actorId;
    protected \DateTimeImmutable $occurredAt;

    public function __construct(string $conversationUuid, int $actorId)
    {
        $this->conversationUuid = $conversationUuid;
        $this->actorId = $actorId;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function conversationUuid(): string
    {
        return $this->conversationUuid;
    }

    public function actorId(): int
    {
        return $this->actorId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
