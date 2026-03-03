<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Entity;

class SlaSnapshot
{
    private ?int $id;
    private string $uuid;
    private ?int $projectId;
    private int $slaOriginalId;
    private int $slaVersionAtBinding;
    private string $slaNameAtBinding;
    private ?int $responseTargetMinutes;
    private ?int $resolutionTargetMinutes;
    private array $calendarSnapshotJson;
    private \DateTimeImmutable $boundAt;
    private ?array $tierSnapshots;
    private ?int $tierTransitionCapPercent;

    public function __construct(
        ?int $projectId,
        int $slaOriginalId,
        int $slaVersionAtBinding,
        string $slaNameAtBinding,
        ?int $responseTargetMinutes,
        ?int $resolutionTargetMinutes,
        array $calendarSnapshotJson,
        ?string $uuid = null,
        ?int $id = null,
        ?\DateTimeImmutable $boundAt = null,
        ?array $tierSnapshots = null,
        ?int $tierTransitionCapPercent = null
    ) {
        $this->id = $id;
        $this->uuid = $uuid ?? $this->generateUuid();
        $this->projectId = $projectId;
        $this->slaOriginalId = $slaOriginalId;
        $this->slaVersionAtBinding = $slaVersionAtBinding;
        $this->slaNameAtBinding = $slaNameAtBinding;
        $this->responseTargetMinutes = $responseTargetMinutes;
        $this->resolutionTargetMinutes = $resolutionTargetMinutes;
        $this->calendarSnapshotJson = $calendarSnapshotJson;
        $this->boundAt = $boundAt ?? new \DateTimeImmutable();
        $this->tierSnapshots = $tierSnapshots;
        $this->tierTransitionCapPercent = $tierTransitionCapPercent;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public function calculateDueDate(\DateTimeImmutable $startUtc, string $type): \DateTimeImmutable
    {
        // This is where the integration with BusinessTimeCalculator would happen
        // Ideally, we don't put the calculation logic IN the entity, but the entity holds the rules.
        // A service like SlaCalculator would take (SlaSnapshot, Ticket) and return DueDate.
        return $startUtc; // Placeholder
    }

    public function calendarSnapshot(): array
    {
        return $this->calendarSnapshotJson;
    }
    
    public function isTiered(): bool
    {
        return !empty($this->tierSnapshots);
    }

    public function responseTargetMinutes(): ?int
    {
        return $this->responseTargetMinutes;
    }
    
    public function resolutionTargetMinutes(): ?int
    {
        return $this->resolutionTargetMinutes;
    }

    public function projectId(): ?int
    {
        return $this->projectId;
    }

    public function tierSnapshots(): ?array { return $this->tierSnapshots; }
    public function tierTransitionCapPercent(): ?int { return $this->tierTransitionCapPercent; }

    /**
     * Find a specific tier snapshot by priority.
     * @return array|null The tier snapshot data or null if not found
     */
    public function findTierByPriority(int $priority): ?array
    {
        if ($this->tierSnapshots === null) {
            return null;
        }
        foreach ($this->tierSnapshots as $tier) {
            if (($tier['priority'] ?? 0) === $priority) {
                return $tier;
            }
        }
        return null;
    }

    public function id(): ?int { return $this->id; }
    public function uuid(): string { return $this->uuid; }
    public function slaOriginalId(): int { return $this->slaOriginalId; }
    public function slaVersionAtBinding(): int { return $this->slaVersionAtBinding; }
    public function slaNameAtBinding(): string { return $this->slaNameAtBinding; }
    public function boundAt(): \DateTimeImmutable { return $this->boundAt; }
}
