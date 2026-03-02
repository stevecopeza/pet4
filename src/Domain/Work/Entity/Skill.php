<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class Skill
{
    private ?int $id;
    private int $capabilityId;
    private string $name;
    private string $description;
    private string $status;
    private ?\DateTimeImmutable $createdAt;

    public function __construct(
        int $capabilityId,
        string $name,
        string $description,
        ?int $id = null,
        string $status = 'active',
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->capabilityId = $capabilityId;
        $this->name = $name;
        $this->description = $description;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function capabilityId(): int
    {
        return $this->capabilityId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
