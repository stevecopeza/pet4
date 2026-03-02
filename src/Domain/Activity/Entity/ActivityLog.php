<?php

declare(strict_types=1);

namespace Pet\Domain\Activity\Entity;

class ActivityLog
{
    private ?int $id;
    private string $type; // e.g., 'project_created', 'ticket_updated'
    private string $description;
    private ?int $userId; // WP User ID of the actor
    private ?string $relatedEntityType; // e.g., 'project', 'ticket'
    private ?int $relatedEntityId;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $type,
        string $description,
        ?int $userId = null,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->description = $description;
        $this->userId = $userId;
        $this->relatedEntityType = $relatedEntityType;
        $this->relatedEntityId = $relatedEntityId;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function relatedEntityType(): ?string
    {
        return $this->relatedEntityType;
    }

    public function relatedEntityId(): ?int
    {
        return $this->relatedEntityId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
