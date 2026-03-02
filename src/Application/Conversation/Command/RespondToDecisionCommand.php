<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

class RespondToDecisionCommand
{
    private string $decisionUuid;
    private string $response;
    private ?string $comment;
    private int $responderId;

    public function __construct(
        string $decisionUuid,
        string $response,
        ?string $comment,
        int $responderId
    ) {
        $this->decisionUuid = $decisionUuid;
        $this->response = $response;
        $this->comment = $comment;
        $this->responderId = $responderId;
    }

    public function decisionUuid(): string { return $this->decisionUuid; }
    public function response(): string { return $this->response; }
    public function comment(): ?string { return $this->comment; }
    public function responderId(): int { return $this->responderId; }
}
