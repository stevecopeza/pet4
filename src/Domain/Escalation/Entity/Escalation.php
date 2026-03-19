<?php

declare(strict_types=1);

namespace Pet\Domain\Escalation\Entity;

use Pet\Domain\Escalation\Event\EscalationAcknowledgedEvent;
use Pet\Domain\Escalation\Event\EscalationResolvedEvent;

class Escalation
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_ACKED = 'ACKED';
    public const STATUS_RESOLVED = 'RESOLVED';

    public const SEVERITY_LOW = 'LOW';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_HIGH = 'HIGH';
    public const SEVERITY_CRITICAL = 'CRITICAL';

    private const ALLOWED_TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_ACKED, self::STATUS_RESOLVED],
        self::STATUS_ACKED => [self::STATUS_RESOLVED],
        self::STATUS_RESOLVED => [],
    ];

    private const VALID_SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
        self::SEVERITY_CRITICAL,
    ];

    private const VALID_SOURCE_TYPES = ['ticket', 'project', 'customer'];

    private ?int $id;
    private string $escalationId;
    private string $sourceEntityType;
    private int $sourceEntityId;
    private string $severity;
    private string $status;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $acknowledgedAt;
    private ?\DateTimeImmutable $resolvedAt;
    private ?int $createdBy;
    private ?int $acknowledgedBy;
    private ?int $resolvedBy;
    private string $reason;
    private string $summary;
    private ?string $resolutionNote;
    private string $metadataJson;
    private ?string $openDedupeKey;

    /** @var object[] */
    private array $domainEvents = [];

    public function __construct(
        string $escalationId,
        string $sourceEntityType,
        int $sourceEntityId,
        string $severity,
        string $reason,
        ?int $createdBy = null,
        string $metadataJson = '{}',
        ?int $id = null,
        string $status = self::STATUS_OPEN,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $acknowledgedAt = null,
        ?\DateTimeImmutable $resolvedAt = null,
        ?int $acknowledgedBy = null,
        ?int $resolvedBy = null,
        ?string $openDedupeKey = null,
        ?string $summary = null,
        ?string $resolutionNote = null
    ) {
        if (!in_array($sourceEntityType, self::VALID_SOURCE_TYPES, true)) {
            throw new \DomainException("Invalid source entity type: $sourceEntityType");
        }
        if (!in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new \DomainException("Invalid severity: $severity");
        }
        if (!array_key_exists($status, self::ALLOWED_TRANSITIONS)) {
            throw new \DomainException("Invalid status: $status");
        }

        $this->id = $id;
        $this->escalationId = $escalationId;
        $this->sourceEntityType = $sourceEntityType;
        $this->sourceEntityId = $sourceEntityId;
        $this->severity = $severity;
        $this->status = $status;
        $this->reason = $reason;
        $this->summary = $summary ?? self::defaultSummary($reason);
        $this->createdBy = $createdBy;
        $this->metadataJson = $metadataJson;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->acknowledgedAt = $acknowledgedAt;
        $this->resolvedAt = $resolvedAt;
        $this->acknowledgedBy = $acknowledgedBy;
        $this->resolvedBy = $resolvedBy;
        $this->resolutionNote = $resolutionNote;

        // Compute dedupe key for non-terminal states; accept explicit value for hydration
        if ($openDedupeKey !== null) {
            $this->openDedupeKey = $openDedupeKey;
        } elseif ($status !== self::STATUS_RESOLVED) {
            $this->openDedupeKey = self::computeDedupeKey($sourceEntityType, $sourceEntityId, $severity, $reason);
        } else {
            $this->openDedupeKey = null;
        }
    }

    // --- Transitions ---

    public function acknowledge(int $actorId): void
    {
        $this->transitionTo(self::STATUS_ACKED);
        $this->acknowledgedBy = $actorId;
        $this->acknowledgedAt = new \DateTimeImmutable();

        $this->recordEvent(new EscalationAcknowledgedEvent(
            $this->escalationId,
            $this->sourceEntityType,
            $this->sourceEntityId,
            $this->severity,
            $actorId
        ));
    }

    public function resolve(int $actorId, ?string $resolutionNote = null): void
    {
        $this->transitionTo(self::STATUS_RESOLVED);
        $this->resolvedBy = $actorId;
        $this->resolvedAt = new \DateTimeImmutable();
        $this->openDedupeKey = null; // Release dedupe slot so future triggers can create new escalations
        $this->resolutionNote = $resolutionNote;

        $this->recordEvent(new EscalationResolvedEvent(
            $this->escalationId,
            $this->sourceEntityType,
            $this->sourceEntityId,
            $this->severity,
            $actorId,
            $resolutionNote
        ));
    }

    private function transitionTo(string $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw new \DomainException(
                "Cannot transition escalation from '{$this->status}' to '$newStatus'. "
                . 'Allowed: ' . ($allowed ? implode(', ', $allowed) : 'none (terminal)')
            );
        }
        $this->status = $newStatus;
    }

    // --- Getters ---

    public function id(): ?int { return $this->id; }
    public function escalationId(): string { return $this->escalationId; }
    public function sourceEntityType(): string { return $this->sourceEntityType; }
    public function sourceEntityId(): int { return $this->sourceEntityId; }
    public function severity(): string { return $this->severity; }
    public function status(): string { return $this->status; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function openedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function acknowledgedAt(): ?\DateTimeImmutable { return $this->acknowledgedAt; }
    public function resolvedAt(): ?\DateTimeImmutable { return $this->resolvedAt; }
    public function createdBy(): ?int { return $this->createdBy; }
    public function acknowledgedBy(): ?int { return $this->acknowledgedBy; }
    public function resolvedBy(): ?int { return $this->resolvedBy; }
    public function reason(): string { return $this->reason; }
    public function summary(): string { return $this->summary; }
    public function resolutionNote(): ?string { return $this->resolutionNote; }
    public function metadataJson(): string { return $this->metadataJson; }
    public function openDedupeKey(): ?string { return $this->openDedupeKey; }
    public function isTerminal(): bool { return $this->status === self::STATUS_RESOLVED; }

    public static function computeDedupeKey(string $sourceEntityType, int $sourceEntityId, string $severity, string $reason): string
    {
        return hash('sha256', "{$sourceEntityType}|{$sourceEntityId}|{$severity}|{$reason}");
    }

    private static function defaultSummary(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return 'Escalation';
        }
        $max = 140;
        if (strlen($reason) <= $max) {
            return $reason;
        }
        return rtrim(substr($reason, 0, $max));
    }

    // --- Events ---

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
