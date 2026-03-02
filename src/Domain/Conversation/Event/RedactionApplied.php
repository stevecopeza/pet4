<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class RedactionApplied extends AbstractConversationEvent
{
    private int $targetEventId;
    private array $fields;
    private string $reason;

    public function __construct(
        string $conversationUuid,
        int $targetEventId,
        array $fields,
        string $reason,
        int $actorId
    ) {
        parent::__construct($conversationUuid, $actorId);
        $this->targetEventId = $targetEventId;
        $this->fields = $fields;
        $this->reason = $reason;
    }

    public function eventType(): string
    {
        return 'RedactionApplied';
    }

    public function payload(): array
    {
        return [
            'target_event_id' => $this->targetEventId,
            'fields' => $this->fields,
            'reason' => $this->reason,
        ];
    }
}
