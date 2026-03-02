<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\ValueObject;

class ContractStatus
{
    public const ACTIVE = 'active';
    public const COMPLETED = 'completed';
    public const TERMINATED = 'terminated';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function terminated(): self
    {
        return new self(self::TERMINATED);
    }

    public static function fromString(string $status): self
    {
        if (!in_array($status, [self::ACTIVE, self::COMPLETED, self::TERMINATED], true)) {
            throw new \InvalidArgumentException("Invalid contract status: $status");
        }
        return new self($status);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function canTransitionTo(self $newState): bool
    {
        // Active can go to Completed or Terminated
        if ($this->value === self::ACTIVE) {
            return in_array($newState->value, [self::COMPLETED, self::TERMINATED], true);
        }

        // Terminal states
        return false;
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, [self::COMPLETED, self::TERMINATED], true);
    }
}
