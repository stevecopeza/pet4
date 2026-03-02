<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Event;

class DecisionResponded extends AbstractDecisionEvent
{
    private string $response; // 'approved', 'rejected'
    private ?string $comment;

    public function __construct(string $decisionUuid, string $response, ?string $comment, int $actorId)
    {
        parent::__construct($decisionUuid, $actorId);
        $this->response = $response;
        $this->comment = $comment;
    }

    public function eventType(): string
    {
        return 'DecisionResponded';
    }

    public function payload(): array
    {
        return [
            'response' => $this->response,
            'comment' => $this->comment,
        ];
    }
}
