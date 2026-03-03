<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Entity;

use Pet\Domain\Calendar\Entity\Calendar;

class SlaDefinition
{
    private ?int $id;
    private string $uuid;
    private string $name;
    private string $status; // 'draft', 'published', 'deprecated'
    private int $versionNumber;
    private ?Calendar $calendar;
    private ?int $responseTargetMinutes;
    private ?int $resolutionTargetMinutes;
    private array $escalationRules; // Array of EscalationRule objects
    /** @var SlaTier[] */
    private array $tiers;
    private int $tierTransitionCapPercent;

    public function __construct(
        string $name,
        ?Calendar $calendar,
        ?int $responseTargetMinutes,
        ?int $resolutionTargetMinutes,
        array $escalationRules = [],
        string $status = 'draft',
        int $versionNumber = 1,
        ?string $uuid = null,
        ?int $id = null,
        array $tiers = [],
        int $tierTransitionCapPercent = 80
    ) {
        $this->id = $id;
        $this->uuid = $uuid ?? $this->generateUuid();
        $this->name = $name;
        $this->calendar = $calendar;
        $this->responseTargetMinutes = $responseTargetMinutes;
        $this->resolutionTargetMinutes = $resolutionTargetMinutes;
        $this->escalationRules = $escalationRules;
        $this->status = $status;
        $this->versionNumber = $versionNumber;
        $this->tiers = $tiers;
        $this->tierTransitionCapPercent = $tierTransitionCapPercent;

        $this->validate();
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

    public function isTiered(): bool
    {
        return !empty($this->tiers);
    }

    private function validate(): void
    {
        if ($this->tierTransitionCapPercent < 1 || $this->tierTransitionCapPercent > 99) {
            throw new \DomainException("Tier transition cap must be between 1 and 99.");
        }

        if ($this->isTiered()) {
            // Tiered mode: flat fields should be null, tiers must be valid
            $priorities = [];
            foreach ($this->tiers as $tier) {
                if (!$tier instanceof SlaTier) {
                    throw new \DomainException("Each tier must be an instance of SlaTier.");
                }
                if (in_array($tier->priority(), $priorities, true)) {
                    throw new \DomainException("Duplicate tier priority: " . $tier->priority());
                }
                $priorities[] = $tier->priority();
            }
        } else {
            // Single-tier mode: flat fields required
            if ($this->responseTargetMinutes === null || $this->resolutionTargetMinutes === null) {
                throw new \DomainException("Single-tier SLA requires response and resolution targets.");
            }
            if ($this->responseTargetMinutes <= 0 || $this->resolutionTargetMinutes <= 0) {
                throw new \DomainException("SLA targets must be positive integers.");
            }
            if ($this->responseTargetMinutes > $this->resolutionTargetMinutes) {
                throw new \DomainException("Response target cannot exceed resolution target.");
            }
        }
    }

    public function publish(): void
    {
        if ($this->status !== 'draft') {
            throw new \DomainException("Only draft SLAs can be published.");
        }

        if ($this->isTiered()) {
            if (empty($this->tiers)) {
                throw new \DomainException("Tiered SLA must have at least one tier to publish.");
            }
            foreach ($this->tiers as $tier) {
                if (empty($tier->escalationRules())) {
                    throw new \DomainException(
                        "Each tier must have escalation rules before publishing. Tier priority: " . $tier->priority()
                    );
                }
            }
        }

        $this->status = 'published';
    }

    public function createSnapshot(?int $projectId): SlaSnapshot
    {
        if ($this->status !== 'published') {
            throw new \DomainException("Cannot bind a non-published SLA to a project.");
        }

        if ($this->isTiered()) {
            // Build per-tier snapshots with calendar data
            $tierSnapshots = [];
            foreach ($this->tiers as $tier) {
                $tierSnapshots[] = [
                    'priority' => $tier->priority(),
                    'label' => $tier->label(),
                    'calendar_id' => $tier->calendarId(),
                    'response_target_minutes' => $tier->responseTargetMinutes(),
                    'resolution_target_minutes' => $tier->resolutionTargetMinutes(),
                    'escalation_rules' => $tier->escalationRules(),
                ];
            }

            return new SlaSnapshot(
                $projectId,
                $this->id,
                $this->versionNumber,
                $this->name,
                null,
                null,
                [], // No single calendar snapshot for tiered
                null,
                null,
                null,
                $tierSnapshots,
                $this->tierTransitionCapPercent
            );
        }

        return new SlaSnapshot(
            $projectId,
            $this->id,
            $this->versionNumber,
            $this->name,
            $this->responseTargetMinutes,
            $this->resolutionTargetMinutes,
            $this->calendar->createSnapshot()
        );
    }

    // Getters...
    public function id(): ?int { return $this->id; }
    public function uuid(): string { return $this->uuid; }
    public function name(): string { return $this->name; }
    public function status(): string { return $this->status; }
    public function versionNumber(): int { return $this->versionNumber; }
    public function calendar(): ?Calendar { return $this->calendar; }
    public function responseTargetMinutes(): ?int { return $this->responseTargetMinutes; }
    public function resolutionTargetMinutes(): ?int { return $this->resolutionTargetMinutes; }
    public function escalationRules(): array { return $this->escalationRules; }
    /** @return SlaTier[] */
    public function tiers(): array { return $this->tiers; }
    public function tierTransitionCapPercent(): int { return $this->tierTransitionCapPercent; }
}
