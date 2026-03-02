<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class Capability
{
    private ?int $id;
    private string $name;
    private string $description;
    private ?int $parentId;
    private string $status;
    private ?\DateTimeImmutable $createdAt;

    public function __construct(
        string $name,
        string $description,
        ?int $id = null,
        ?int $parentId = null,
        string $status = 'active',
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->parentId = $parentId;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function parentId(): ?int
    {
        return $this->parentId;
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
