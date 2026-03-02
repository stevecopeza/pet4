<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class MessagePosted extends AbstractConversationEvent
{
    private string $body;
    private array $mentions;
    private array $attachments;
    private ?int $replyToMessageId;

    public function __construct(
        string $conversationUuid,
        string $body,
        array $mentions,
        array $attachments,
        int $actorId,
        ?int $replyToMessageId = null
    ) {
        parent::__construct($conversationUuid, $actorId);
        $this->body = $body;
        $this->mentions = $mentions;
        $this->attachments = $attachments;
        $this->replyToMessageId = $replyToMessageId;
    }

    public function eventType(): string
    {
        return 'MessagePosted';
    }

    public function payload(): array
    {
        return [
            'body' => $this->body,
            'mentions' => $this->mentions,
            'attachments' => $this->attachments,
            'reply_to_message_id' => $this->replyToMessageId,
        ];
    }
    
    public function replyToMessageId(): ?int
    {
        return $this->replyToMessageId;
    }
}
