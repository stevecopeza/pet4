<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

class CreateConversationCommand
{
    private string $contextType;
    private string $contextId;
    private string $subject;
    private string $subjectKey;
    private int $actorId;
    private ?string $contextVersion;
    private array $initialUserIds;
    private array $initialContactIds;
    private array $initialTeamIds;

    public function __construct(
        string $contextType,
        string $contextId,
        string $subject,
        string $subjectKey,
        int $actorId,
        ?string $contextVersion = null,
        array $initialUserIds = [],
        array $initialContactIds = [],
        array $initialTeamIds = []
    ) {
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->subject = $subject;
        $this->subjectKey = $subjectKey;
        $this->actorId = $actorId;
        $this->contextVersion = $contextVersion;
        $this->initialUserIds = $initialUserIds;
        $this->initialContactIds = $initialContactIds;
        $this->initialTeamIds = $initialTeamIds;
    }

    public function contextType(): string { return $this->contextType; }
    public function contextId(): string { return $this->contextId; }
    public function subject(): string { return $this->subject; }
    public function subjectKey(): string { return $this->subjectKey; }
    public function actorId(): int { return $this->actorId; }
    public function contextVersion(): ?string { return $this->contextVersion; }
    public function initialUserIds(): array { return $this->initialUserIds; }
    public function initialContactIds(): array { return $this->initialContactIds; }
    public function initialTeamIds(): array { return $this->initialTeamIds; }
}
