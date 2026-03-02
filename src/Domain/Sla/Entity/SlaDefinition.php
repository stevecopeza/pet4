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
    private Calendar $calendar;
    private int $responseTargetMinutes;
    private int $resolutionTargetMinutes;
    private array $escalationRules; // Array of EscalationRule objects

    public function __construct(
        string $name,
        Calendar $calendar,
        int $responseTargetMinutes,
        int $resolutionTargetMinutes,
        array $escalationRules = [],
        string $status = 'draft',
        int $versionNumber = 1,
        ?string $uuid = null,
        ?int $id = null
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

    private function validate(): void
    {
        if ($this->responseTargetMinutes <= 0 || $this->resolutionTargetMinutes <= 0) {
            throw new \DomainException("SLA targets must be positive integers.");
        }
        if ($this->responseTargetMinutes > $this->resolutionTargetMinutes) {
            throw new \DomainException("Response target cannot exceed resolution target.");
        }
    }

    public function publish(): void
    {
        if ($this->status !== 'draft') {
            throw new \DomainException("Only draft SLAs can be published.");
        }
        $this->status = 'published';
    }

    public function createSnapshot(?int $projectId): SlaSnapshot
    {
        if ($this->status !== 'published') {
            throw new \DomainException("Cannot bind a non-published SLA to a project.");
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
    public function calendar(): Calendar { return $this->calendar; }
    public function responseTargetMinutes(): int { return $this->responseTargetMinutes; }
    public function resolutionTargetMinutes(): int { return $this->resolutionTargetMinutes; }
    public function escalationRules(): array { return $this->escalationRules; }
}
