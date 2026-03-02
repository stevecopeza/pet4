<?php

namespace Pet\Domain\Feed\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

class FeedEvent
{
    public function __construct(
        private string $id,
        private string $eventType,
        private string $sourceEngine,
        private string $sourceEntityId,
        private string $classification,
        private string $title,
        private string $summary,
        private array $metadata,
        private string $audienceScope,
        private ?string $audienceReferenceId,
        private bool $pinned,
        private ?DateTimeImmutable $expiresAt,
        private DateTimeImmutable $createdAt
    ) {
        $this->validateClassification($classification);
        $this->validateAudienceScope($audienceScope);
    }

    public static function create(
        string $id,
        string $eventType,
        string $sourceEngine,
        string $sourceEntityId,
        string $classification,
        string $title,
        string $summary,
        array $metadata,
        string $audienceScope,
        ?string $audienceReferenceId,
        bool $pinned = false,
        ?DateTimeImmutable $expiresAt = null
    ): self {
        return new self(
            $id,
            $eventType,
            $sourceEngine,
            $sourceEntityId,
            $classification,
            $title,
            $summary,
            $metadata,
            $audienceScope,
            $audienceReferenceId,
            $pinned,
            $expiresAt,
            new DateTimeImmutable()
        );
    }

    private function validateClassification(string $classification): void
    {
        $allowed = ['critical', 'operational', 'informational', 'strategic'];
        if (!in_array($classification, $allowed)) {
            throw new InvalidArgumentException("Invalid classification: $classification");
        }
    }

    private function validateAudienceScope(string $scope): void
    {
        $allowed = ['global', 'department', 'role', 'user'];
        if (!in_array($scope, $allowed)) {
            throw new InvalidArgumentException("Invalid audience scope: $scope");
        }
    }

    public function getId(): string { return $this->id; }
    public function getEventType(): string { return $this->eventType; }
    public function getSourceEngine(): string { return $this->sourceEngine; }
    public function getSourceEntityId(): string { return $this->sourceEntityId; }
    public function getClassification(): string { return $this->classification; }
    public function getTitle(): string { return $this->title; }
    public function getSummary(): string { return $this->summary; }
    public function getMetadata(): array { return $this->metadata; }
    public function getAudienceScope(): string { return $this->audienceScope; }
    public function getAudienceReferenceId(): ?string { return $this->audienceReferenceId; }
    public function isPinned(): bool { return $this->pinned; }
    public function getExpiresAt(): ?DateTimeImmutable { return $this->expiresAt; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
