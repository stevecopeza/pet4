<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\ValueObject;

class QuoteState
{
    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const ARCHIVED = 'archived';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function sent(): self
    {
        return new self(self::SENT);
    }

    public static function accepted(): self
    {
        return new self(self::ACCEPTED);
    }

    public static function rejected(): self
    {
        return new self(self::REJECTED);
    }

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    public static function fromString(string $status): self
    {
        if (!in_array($status, [self::DRAFT, self::SENT, self::ACCEPTED, self::REJECTED, self::ARCHIVED], true)) {
            throw new \InvalidArgumentException("Invalid quote state: $status");
        }
        return new self($status);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function canTransitionTo(self $newState): bool
    {
        // Transitions from Draft
        if ($this->value === self::DRAFT) {
            return in_array($newState->value, [self::SENT, self::ARCHIVED], true);
        }

        // Transitions from Sent
        if ($this->value === self::SENT) {
            return in_array($newState->value, [self::ACCEPTED, self::REJECTED, self::ARCHIVED, self::DRAFT], true); // Allow revert to draft?
        }

        // Terminal states
        return false;
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, [self::ACCEPTED, self::REJECTED, self::ARCHIVED], true);
    }
}
