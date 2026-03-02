<?php

declare(strict_types=1);

namespace Pet\Application\Activity\Dto;

final class ActivityEvent
{
    public string $id;
    public string $occurredAt;
    public string $actorType;
    public ?string $actorId;
    public string $actorDisplayName;
    public ?string $actorAvatarUrl;
    public string $eventType;
    public string $severity;
    public string $referenceType;
    public string $referenceId;
    public ?string $referenceUrl;
    public ?string $customerId;
    public ?string $customerName;
    public ?string $companyLogoUrl;
    public string $headline;
    public ?string $subline;
    public array $tags;
    public ?array $sla;
    public array $meta;

    public function __construct(
        string $id,
        string $occurredAt,
        string $actorType,
        ?string $actorId,
        string $actorDisplayName,
        ?string $actorAvatarUrl,
        string $eventType,
        string $severity,
        string $referenceType,
        string $referenceId,
        ?string $referenceUrl,
        ?string $customerId,
        ?string $customerName,
        ?string $companyLogoUrl,
        string $headline,
        ?string $subline,
        array $tags,
        ?array $sla,
        array $meta
    ) {
        $this->id = $id;
        $this->occurredAt = $occurredAt;
        $this->actorType = $actorType;
        $this->actorId = $actorId;
        $this->actorDisplayName = $actorDisplayName;
        $this->actorAvatarUrl = $actorAvatarUrl;
        $this->eventType = $eventType;
        $this->severity = $severity;
        $this->referenceType = $referenceType;
        $this->referenceId = $referenceId;
        $this->referenceUrl = $referenceUrl;
        $this->customerId = $customerId;
        $this->customerName = $customerName;
        $this->companyLogoUrl = $companyLogoUrl;
        $this->headline = $headline;
        $this->subline = $subline;
        $this->tags = $tags;
        $this->sla = $sla;
        $this->meta = $meta;
    }
}

