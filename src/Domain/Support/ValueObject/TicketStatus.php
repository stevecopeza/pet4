<?php

declare(strict_types=1);

namespace Pet\Domain\Support\ValueObject;

/**
 * Context-governed ticket status value object.
 * Encapsulates valid statuses and transition rules per lifecycle_owner.
 */
class TicketStatus
{
    // Support statuses
    public const NEW = 'new';
    public const OPEN = 'open';
    public const PENDING = 'pending';
    public const RESOLVED = 'resolved';

    // Project statuses
    public const PLANNED = 'planned';
    public const READY = 'ready';
    public const IN_PROGRESS = 'in_progress';
    public const BLOCKED = 'blocked';
    public const DONE = 'done';

    // Shared
    public const CLOSED = 'closed';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Valid statuses per lifecycle context.
     */
    private static function statusesForContext(string $lifecycleOwner): array
    {
        return match ($lifecycleOwner) {
            'support' => [self::NEW, self::OPEN, self::PENDING, self::RESOLVED, self::CLOSED],
            'project' => [self::PLANNED, self::READY, self::IN_PROGRESS, self::BLOCKED, self::DONE, self::CLOSED],
            'internal' => [self::PLANNED, self::IN_PROGRESS, self::DONE, self::CLOSED],
            default => throw new \InvalidArgumentException("Unknown lifecycle owner: $lifecycleOwner"),
        };
    }

    /**
     * Transition map per lifecycle context.
     * Key = current status, Value = array of allowed next statuses.
     */
    private static function transitionMap(string $lifecycleOwner): array
    {
        return match ($lifecycleOwner) {
            'support' => [
                self::NEW => [self::OPEN],
                self::OPEN => [self::PENDING, self::RESOLVED, self::CLOSED],
                self::PENDING => [self::OPEN, self::RESOLVED, self::CLOSED],
                self::RESOLVED => [self::CLOSED, self::OPEN],
                self::CLOSED => [],
            ],
            'project' => [
                self::PLANNED => [self::READY],
                self::READY => [self::IN_PROGRESS],
                self::IN_PROGRESS => [self::BLOCKED, self::DONE],
                self::BLOCKED => [self::IN_PROGRESS],
                self::DONE => [self::CLOSED],
                self::CLOSED => [],
            ],
            'internal' => [
                self::PLANNED => [self::IN_PROGRESS],
                self::IN_PROGRESS => [self::DONE],
                self::DONE => [self::CLOSED],
                self::CLOSED => [],
            ],
            default => throw new \InvalidArgumentException("Unknown lifecycle owner: $lifecycleOwner"),
        };
    }

    /**
     * Create from string, validating the status is valid for the given lifecycle context.
     */
    public static function fromString(string $status, string $lifecycleOwner): self
    {
        $allowed = self::statusesForContext($lifecycleOwner);
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Invalid ticket status '$status' for lifecycle '$lifecycleOwner'. Allowed: " . implode(', ', $allowed)
            );
        }
        return new self($status);
    }

    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Returns the list of statuses this status can transition to within the given lifecycle.
     */
    public function allowedTransitions(string $lifecycleOwner): array
    {
        $map = self::transitionMap($lifecycleOwner);
        return $map[$this->value] ?? [];
    }

    /**
     * Check if a transition to the given status is valid.
     */
    public function canTransitionTo(string $newStatus, string $lifecycleOwner): bool
    {
        return in_array($newStatus, $this->allowedTransitions($lifecycleOwner), true);
    }

    /**
     * Whether this status is terminal (no further transitions allowed).
     */
    public function isTerminal(string $lifecycleOwner): bool
    {
        return empty($this->allowedTransitions($lifecycleOwner));
    }

    /**
     * Returns all valid statuses for a given lifecycle context.
     */
    public static function allForContext(string $lifecycleOwner): array
    {
        return self::statusesForContext($lifecycleOwner);
    }
}
