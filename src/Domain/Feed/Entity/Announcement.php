<?php

namespace Pet\Domain\Feed\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

class Announcement
{
    public function __construct(
        private string $id,
        private string $title,
        private string $body,
        private string $priorityLevel,
        private bool $pinned,
        private bool $acknowledgementRequired,
        private bool $gpsRequired,
        private ?DateTimeImmutable $acknowledgementDeadline,
        private string $audienceScope,
        private ?string $audienceReferenceId,
        private string $authorUserId,
        private ?DateTimeImmutable $expiresAt,
        private DateTimeImmutable $createdAt
    ) {
        $this->validatePriority($priorityLevel);
        $this->validateAudienceScope($audienceScope);
    }

    public static function create(
        string $id,
        string $title,
        string $body,
        string $priorityLevel,
        bool $pinned,
        bool $acknowledgementRequired,
        bool $gpsRequired,
        ?DateTimeImmutable $acknowledgementDeadline,
        string $audienceScope,
        ?string $audienceReferenceId,
        string $authorUserId,
        ?DateTimeImmutable $expiresAt
    ): self {
        return new self(
            $id,
            $title,
            $body,
            $priorityLevel,
            $pinned,
            $acknowledgementRequired,
            $gpsRequired,
            $acknowledgementDeadline,
            $audienceScope,
            $audienceReferenceId,
            $authorUserId,
            $expiresAt,
            new DateTimeImmutable()
        );
    }

    private function validatePriority(string $priority): void
    {
        $allowed = ['low', 'normal', 'high', 'critical'];
        if (!in_array($priority, $allowed)) {
            throw new InvalidArgumentException("Invalid priority level: $priority");
        }
    }

    private function validateAudienceScope(string $scope): void
    {
        $allowed = ['global', 'department', 'role'];
        if (!in_array($scope, $allowed)) {
            throw new InvalidArgumentException("Invalid audience scope: $scope");
        }
    }

    public function getId(): string { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getBody(): string { return $this->body; }
    public function getPriorityLevel(): string { return $this->priorityLevel; }
    public function isPinned(): bool { return $this->pinned; }
    public function isAcknowledgementRequired(): bool { return $this->acknowledgementRequired; }
    public function isGpsRequired(): bool { return $this->gpsRequired; }
    public function getAcknowledgementDeadline(): ?DateTimeImmutable { return $this->acknowledgementDeadline; }
    public function getAudienceScope(): string { return $this->audienceScope; }
    public function getAudienceReferenceId(): ?string { return $this->audienceReferenceId; }
    public function getAuthorUserId(): string { return $this->authorUserId; }
    public function getExpiresAt(): ?DateTimeImmutable { return $this->expiresAt; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
