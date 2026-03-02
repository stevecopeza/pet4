<?php

namespace Pet\Domain\Feed\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

class FeedReaction
{
    public function __construct(
        private string $id,
        private string $feedEventId,
        private string $userId,
        private string $reactionType,
        private DateTimeImmutable $createdAt
    ) {
        $this->validateReactionType($reactionType);
    }

    public static function create(
        string $id,
        string $feedEventId,
        string $userId,
        string $reactionType
    ): self {
        return new self(
            $id,
            $feedEventId,
            $userId,
            $reactionType,
            new DateTimeImmutable()
        );
    }

    private function validateReactionType(string $type): void
    {
        $allowed = ['acknowledged', 'concern', 'suggestion', 'win'];
        if (!in_array($type, $allowed)) {
            throw new InvalidArgumentException("Invalid reaction type: $type");
        }
    }

    public function getId(): string { return $this->id; }
    public function getFeedEventId(): string { return $this->feedEventId; }
    public function getUserId(): string { return $this->userId; }
    public function getReactionType(): string { return $this->reactionType; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
