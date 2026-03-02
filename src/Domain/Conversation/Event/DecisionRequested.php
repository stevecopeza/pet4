<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

use Pet\Domain\Conversation\ValueObject\ApprovalPolicy;

class DecisionRequested extends AbstractDecisionEvent
{
    private string $conversationUuid;
    private string $decisionType;
    private array $payload;
    private ApprovalPolicy $policy;

    public function __construct(
        string $decisionUuid,
        string $conversationUuid,
        string $decisionType,
        array $payload,
        ApprovalPolicy $policy,
        int $actorId
    ) {
        parent::__construct($decisionUuid, $actorId);
        $this->conversationUuid = $conversationUuid;
        $this->decisionType = $decisionType;
        $this->payload = $payload;
        $this->policy = $policy;
    }

    public function eventType(): string
    {
        return 'DecisionRequested';
    }

    public function payload(): array
    {
        return [
            'conversation_uuid' => $this->conversationUuid,
            'decision_type' => $this->decisionType,
            'payload' => $this->payload,
            'policy' => $this->policy->jsonSerialize(),
        ];
    }

    public function conversationUuid(): string
    {
        return $this->conversationUuid;
    }
}
