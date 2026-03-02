<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

interface ConversationEvent
{
    public function eventType(): string;
    public function payload(): array;
    public function occurredAt(): \DateTimeImmutable;
    public function actorId(): int;
    public function conversationUuid(): string;
}
