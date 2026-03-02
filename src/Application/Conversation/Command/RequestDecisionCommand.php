<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Domain\Conversation\ValueObject\ApprovalPolicy;

class RequestDecisionCommand
{
    private string $conversationUuid;
    private string $decisionType;
    private array $payload;
    private ApprovalPolicy $policy;
    private int $requesterId;

    public function __construct(
        string $conversationUuid,
        string $decisionType,
        array $payload,
        ApprovalPolicy $policy,
        int $requesterId
    ) {
        $this->conversationUuid = $conversationUuid;
        $this->decisionType = $decisionType;
        $this->payload = $payload;
        $this->policy = $policy;
        $this->requesterId = $requesterId;
    }

    public function conversationUuid(): string { return $this->conversationUuid; }
    public function decisionType(): string { return $this->decisionType; }
    public function payload(): array { return $this->payload; }
    public function policy(): ApprovalPolicy { return $this->policy; }
    public function requesterId(): int { return $this->requesterId; }
}
