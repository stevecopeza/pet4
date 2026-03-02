<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Entity;

use Pet\Domain\Conversation\Event\ConversationCreated;
use Pet\Domain\Conversation\Event\ConversationReopened;
use Pet\Domain\Conversation\Event\ConversationResolved;
use Pet\Domain\Conversation\Event\MessagePosted;
use Pet\Domain\Conversation\Event\ParticipantAdded;
use Pet\Domain\Conversation\Event\ParticipantRemoved;
use Pet\Domain\Conversation\Event\ContactParticipantAdded;
use Pet\Domain\Conversation\Event\ContactParticipantRemoved;
use Pet\Domain\Conversation\Event\TeamParticipantAdded;
use Pet\Domain\Conversation\Event\TeamParticipantRemoved;
use Pet\Domain\Conversation\Event\RedactionApplied;
use Pet\Domain\Conversation\Event\ConversationEvent;

class Conversation
{
    private ?int $id;
    private string $uuid;
    private string $contextType;
    private string $contextId;
    private string $subject;
    private string $subjectKey;
    private string $state;
    private ?string $contextVersion;
    private \DateTimeImmutable $createdAt;
    
    /** @var ConversationEvent[] */
    private array $pendingEvents = [];

    public function __construct(
        ?int $id,
        string $uuid,
        string $contextType,
        string $contextId,
        string $subject,
        string $subjectKey,
        string $state,
        \DateTimeImmutable $createdAt,
        ?string $contextVersion = null
    ) {
        $this->id = $id;
        $this->uuid = $uuid;
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->subject = $subject;
        $this->subjectKey = $subjectKey;
        $this->state = $state;
        $this->createdAt = $createdAt;
        $this->contextVersion = $contextVersion;
    }

    public static function create(
        string $uuid,
        string $contextType,
        string $contextId,
        string $subject,
        string $subjectKey,
        int $actorId,
        ?string $contextVersion = null
    ): self {
        $instance = new self(null, $uuid, $contextType, $contextId, $subject, $subjectKey, 'open', new \DateTimeImmutable(), $contextVersion);
        $instance->recordEvent(new ConversationCreated(
            $uuid,
            $contextType,
            $contextId,
            $subject,
            $subjectKey,
            $actorId
        ));
        return $instance;
    }

    public function contextVersion(): ?string
    {
        return $this->contextVersion;
    }

    public function postMessage(string $body, array $mentions, array $attachments, int $actorId, ?int $replyToMessageId = null): void
    {
        if ($this->state === 'resolved') {
            throw new \DomainException('Cannot post message to resolved conversation');
        }

        $this->recordEvent(new MessagePosted(
            $this->uuid,
            $body,
            $mentions,
            $attachments,
            $actorId,
            $replyToMessageId
        ));
    }

    public function addReaction(int $messageId, string $reactionType, int $actorId): void
    {
        // Idempotency check happens in handler/repo before calling this, 
        // or we just record it and projection handles deduplication.
        // Given constraints, we'll record it. Handler should prevent obvious spam.
        $this->recordEvent(new \Pet\Domain\Conversation\Event\ReactionAdded(
            $this->uuid,
            $messageId,
            $reactionType,
            $actorId
        ));
    }

    public function removeReaction(int $messageId, string $reactionType, int $actorId): void
    {
        $this->recordEvent(new \Pet\Domain\Conversation\Event\ReactionRemoved(
            $this->uuid,
            $messageId,
            $reactionType,
            $actorId
        ));
    }

    public function addParticipant(int $userId, int $actorId): void
    {
        $this->recordEvent(new ParticipantAdded(
            $this->uuid,
            $userId,
            $actorId
        ));
    }

    public function removeParticipant(int $userId, int $actorId): void
    {
        $this->recordEvent(new ParticipantRemoved(
            $this->uuid,
            $userId,
            $actorId
        ));
    }

    public function addContactParticipant(int $contactId, int $actorId): void
    {
        $this->recordEvent(new ContactParticipantAdded(
            $this->uuid,
            $contactId,
            $actorId
        ));
    }

    public function removeContactParticipant(int $contactId, int $actorId): void
    {
        $this->recordEvent(new ContactParticipantRemoved(
            $this->uuid,
            $contactId,
            $actorId
        ));
    }

    public function addTeamParticipant(int $teamId, int $actorId): void
    {
        $this->recordEvent(new TeamParticipantAdded(
            $this->uuid,
            $teamId,
            $actorId
        ));
    }

    public function removeTeamParticipant(int $teamId, int $actorId): void
    {
        $this->recordEvent(new TeamParticipantRemoved(
            $this->uuid,
            $teamId,
            $actorId
        ));
    }

    public function resolve(int $actorId): void
    {
        if ($this->state === 'resolved') {
            return;
        }
        $this->state = 'resolved';
        $this->recordEvent(new ConversationResolved($this->uuid, $actorId));
    }

    public function reopen(int $actorId): void
    {
        if ($this->state === 'open') {
            return;
        }
        $this->state = 'open';
        $this->recordEvent(new ConversationReopened($this->uuid, $actorId));
    }

    public function redact(int $eventId, array $fields, string $reason, int $actorId): void
    {
        $this->recordEvent(new RedactionApplied(
            $this->uuid,
            $eventId,
            $fields,
            $reason,
            $actorId
        ));
    }

    private function recordEvent(ConversationEvent $event): void
    {
        $this->pendingEvents[] = $event;
    }

    public function releaseEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];
        return $events;
    }

    // Getters
    public function id(): ?int { return $this->id; }
    public function uuid(): string { return $this->uuid; }
    public function contextType(): string { return $this->contextType; }
    public function contextId(): string { return $this->contextId; }
    public function subject(): string { return $this->subject; }
    public function subjectKey(): string { return $this->subjectKey; }
    public function state(): string { return $this->state; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
}
