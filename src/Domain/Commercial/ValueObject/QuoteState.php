<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\ValueObject;

class QuoteState
{
    public const DRAFT            = 'draft';
    public const PENDING_APPROVAL = 'pending_approval';
    public const APPROVED         = 'approved';
    public const SENT             = 'sent';
    public const ACCEPTED         = 'accepted';
    public const REJECTED         = 'rejected';   // customer rejection
    public const ARCHIVED         = 'archived';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function draft(): self            { return new self(self::DRAFT); }
    public static function pendingApproval(): self  { return new self(self::PENDING_APPROVAL); }
    public static function approved(): self         { return new self(self::APPROVED); }
    public static function sent(): self             { return new self(self::SENT); }
    public static function accepted(): self         { return new self(self::ACCEPTED); }
    public static function rejected(): self         { return new self(self::REJECTED); }
    public static function archived(): self         { return new self(self::ARCHIVED); }

    public static function fromString(string $status): self
    {
        $valid = [
            self::DRAFT,
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::SENT,
            self::ACCEPTED,
            self::REJECTED,
            self::ARCHIVED,
        ];

        if (!in_array($status, $valid, true)) {
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
        return match ($this->value) {
            // Draft: submit for approval OR send directly (if no approval required) OR archive
            self::DRAFT => in_array($newState->value, [
                self::PENDING_APPROVAL,
                self::SENT,
                self::ARCHIVED,
            ], true),

            // Pending approval: manager approves, manager rejects (returns to draft), or archived
            self::PENDING_APPROVAL => in_array($newState->value, [
                self::APPROVED,
                self::DRAFT,     // rejection — returns to draft with a note
                self::ARCHIVED,
            ], true),

            // Approved: sales person sends, or reverts to draft if they want to make changes
            self::APPROVED => in_array($newState->value, [
                self::SENT,
                self::DRAFT,
                self::ARCHIVED,
            ], true),

            // Sent: customer accepts, customer rejects, revert to draft, or archive
            self::SENT => in_array($newState->value, [
                self::ACCEPTED,
                self::REJECTED,
                self::DRAFT,
                self::ARCHIVED,
            ], true),

            // Terminal states — no further transitions
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, [self::ACCEPTED, self::REJECTED, self::ARCHIVED], true);
    }

    public function requiresApprovalStep(): bool
    {
        return $this->value === self::PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->value === self::APPROVED;
    }

    public function isPendingApproval(): bool
    {
        return $this->value === self::PENDING_APPROVAL;
    }
}
