<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

class PostMessageCommand
{
    private string $conversationUuid;
    private string $body;
    private array $mentions;
    private array $attachments;
    private int $actorId;
    private ?int $replyToMessageId;

    public function __construct(
        string $conversationUuid,
        string $body,
        array $mentions,
        array $attachments,
        int $actorId,
        ?int $replyToMessageId = null
    ) {
        $this->conversationUuid = $conversationUuid;
        $this->body = $body;
        $this->mentions = $mentions;
        $this->attachments = $attachments;
        $this->actorId = $actorId;
        $this->replyToMessageId = $replyToMessageId;
    }

    public function conversationUuid(): string { return $this->conversationUuid; }
    public function body(): string { return $this->body; }
    public function mentions(): array { return $this->mentions; }
    public function attachments(): array { return $this->attachments; }
    public function actorId(): int { return $this->actorId; }
    public function replyToMessageId(): ?int { return $this->replyToMessageId; }
}
