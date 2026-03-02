<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\ValueObject;

class ProjectState
{
    public const PLANNED = 'planned';
    public const ACTIVE = 'active';
    public const ON_HOLD = 'on_hold';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function planned(): self
    {
        return new self(self::PLANNED);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function onHold(): self
    {
        return new self(self::ON_HOLD);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function fromString(string $status): self
    {
        if (!in_array($status, [self::PLANNED, self::ACTIVE, self::ON_HOLD, self::COMPLETED, self::CANCELLED], true)) {
            throw new \InvalidArgumentException("Invalid project state: $status");
        }
        return new self($status);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, [self::COMPLETED, self::CANCELLED], true);
    }
}
