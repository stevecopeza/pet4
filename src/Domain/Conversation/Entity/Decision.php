<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Entity;

use Pet\Domain\Conversation\Event\DecisionCancelled;
use Pet\Domain\Conversation\Event\DecisionEvent;
use Pet\Domain\Conversation\Event\DecisionFinalized;
use Pet\Domain\Conversation\Event\DecisionRequested;
use Pet\Domain\Conversation\Event\DecisionResponded;
use Pet\Domain\Conversation\ValueObject\ApprovalPolicy;

class Decision
{
    private ?int $id;
    private string $uuid;
    private int $conversationId;
    private string $decisionType;
    private string $state;
    private array $payload;
    private ApprovalPolicy $policy;
    private \DateTimeImmutable $requestedAt;
    private int $requesterId;
    private ?\DateTimeImmutable $finalizedAt;
    private ?int $finalizerId;
    private ?string $outcome;
    private ?string $outcomeComment;
    
    /** @var DecisionEvent[] */
    private array $pendingEvents = [];

    /** @var array<int, string> Map of user_id => response ('approved'/'rejected') */
    private array $existingResponses = [];

    public function __construct(
        ?int $id,
        string $uuid,
        int $conversationId,
        string $decisionType,
        string $state,
        array $payload,
        ApprovalPolicy $policy,
        \DateTimeImmutable $requestedAt,
        int $requesterId,
        ?\DateTimeImmutable $finalizedAt,
        ?int $finalizerId,
        ?string $outcome,
        ?string $outcomeComment
    ) {
        $this->id = $id;
        $this->uuid = $uuid;
        $this->conversationId = $conversationId;
        $this->decisionType = $decisionType;
        $this->state = $state;
        $this->payload = $payload;
        $this->policy = $policy;
        $this->requestedAt = $requestedAt;
        $this->requesterId = $requesterId;
        $this->finalizedAt = $finalizedAt;
        $this->finalizerId = $finalizerId;
        $this->outcome = $outcome;
        $this->outcomeComment = $outcomeComment;
    }

    public function setExistingResponses(array $responses): void
    {
        $this->existingResponses = $responses;
    }

    public function hasUserResponded(int $userId): bool
    {
        return isset($this->existingResponses[$userId]);
    }

    public static function request(
        string $uuid,
        string $conversationUuid, // Needed for event
        int $conversationId,      // Needed for state
        string $decisionType,
        array $payload,
        ApprovalPolicy $policy,
        int $requesterId
    ): self {
        $instance = new self(
            null,
            $uuid,
            $conversationId,
            $decisionType,
            'pending',
            $payload,
            $policy,
            new \DateTimeImmutable(),
            $requesterId,
            null,
            null,
            null,
            null
        );

        $instance->recordEvent(new DecisionRequested(
            $uuid,
            $conversationUuid,
            $decisionType,
            $payload,
            $policy,
            $requesterId
        ));

        return $instance;
    }

    public function respond(int $responderId, string $response, ?string $comment): void
    {
        if ($this->state !== 'pending') {
            throw new \DomainException('Decision is already finalized.');
        }

        if (!$this->policy->isEligible($responderId)) {
            throw new \DomainException('User is not an eligible approver.');
        }

        if (isset($this->existingResponses[$responderId])) {
            // Idempotent if same response, else error?
            if ($this->existingResponses[$responderId] === $response) {
                return;
            }
            throw new \DomainException('User has already responded.');
        }

        if (!in_array($response, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException("Invalid response: $response");
        }

        $this->recordEvent(new DecisionResponded(
            $this->uuid,
            $response,
            $comment,
            $responderId
        ));

        // Update local state for immediate evaluation
        $this->existingResponses[$responderId] = $response;

        $this->evaluate($responderId);
    }

    private function evaluate(int $lastResponderId): void
    {
        $mode = $this->policy->mode();
        $responses = $this->existingResponses;
        
        $approvedCount = 0;
        $rejectedCount = 0;
        foreach ($responses as $r) {
            if ($r === 'approved') $approvedCount++;
            if ($r === 'rejected') $rejectedCount++;
        }

        $outcome = null;

        if ($mode === 'any_of') {
            // First response decides
            if ($approvedCount > 0) $outcome = 'approved';
            elseif ($rejectedCount > 0) $outcome = 'rejected';
        } elseif ($mode === 'all_of') {
            // Any reject -> rejected
            if ($rejectedCount > 0) {
                $outcome = 'rejected';
            } elseif ($approvedCount === count($this->policy->eligibleUserIds())) {
                // All approved
                $outcome = 'approved';
            }
        }

        if ($outcome) {
            $this->finalize($outcome, $lastResponderId);
        }
    }

    private function finalize(string $outcome, int $finalizerId): void
    {
        $this->state = $outcome;
        $this->finalizedAt = new \DateTimeImmutable();
        $this->finalizerId = $finalizerId;
        $this->outcome = $outcome;

        $this->recordEvent(new DecisionFinalized(
            $this->uuid,
            $outcome,
            $finalizerId
        ));
    }

    public function cancel(int $actorId, string $reason = 'Cancelled by user'): void
    {
        if ($this->state !== 'pending') {
            return;
        }

        $this->state = 'cancelled';
        $this->finalizedAt = new \DateTimeImmutable();
        $this->finalizerId = $actorId;
        $this->outcome = 'cancelled';
        $this->outcomeComment = $reason;

        $this->recordEvent(new DecisionCancelled(
            $this->uuid,
            $reason,
            $actorId
        ));
    }

    private function recordEvent(DecisionEvent $event): void
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
    public function conversationId(): int { return $this->conversationId; }
    public function decisionType(): string { return $this->decisionType; }
    public function state(): string { return $this->state; }
    public function payload(): array { return $this->payload; }
    public function policy(): ApprovalPolicy { return $this->policy; }
    public function requestedAt(): \DateTimeImmutable { return $this->requestedAt; }
    public function requesterId(): int { return $this->requesterId; }
    public function finalizedAt(): ?\DateTimeImmutable { return $this->finalizedAt; }
    public function finalizerId(): ?int { return $this->finalizerId; }
    public function outcome(): ?string { return $this->outcome; }
    public function outcomeComment(): ?string { return $this->outcomeComment; }
}
