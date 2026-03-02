<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class ConversationCreated extends AbstractConversationEvent
{
    private string $contextType;
    private string $contextId;
    private string $subject;
    private string $subjectKey;

    public function __construct(
        string $conversationUuid,
        string $contextType,
        string $contextId,
        string $subject,
        string $subjectKey,
        int $actorId
    ) {
        parent::__construct($conversationUuid, $actorId);
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->subject = $subject;
        $this->subjectKey = $subjectKey;
    }

    public function eventType(): string
    {
        return 'ConversationCreated';
    }

    public function payload(): array
    {
        return [
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'subject' => $this->subject,
            'subject_key' => $this->subjectKey,
        ];
    }
}
