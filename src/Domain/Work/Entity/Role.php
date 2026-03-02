<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class Role
{
    private ?int $id;
    private string $name;
    private int $version;
    private string $status;
    private string $level;
    private string $description;
    private string $successCriteria;
    private array $requiredSkills; // Array of [skillId => [minLevel, weight]]
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $publishedAt;

    public function __construct(
        string $name,
        string $level,
        string $description,
        string $successCriteria,
        ?int $id = null,
        int $version = 1,
        string $status = 'draft',
        array $requiredSkills = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $publishedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->level = $level;
        $this->description = $description;
        $this->successCriteria = $successCriteria;
        $this->version = $version;
        $this->status = $status;
        $this->requiredSkills = $requiredSkills;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->publishedAt = $publishedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function level(): string
    {
        return $this->level;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function successCriteria(): string
    {
        return $this->successCriteria;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function requiredSkills(): array
    {
        return $this->requiredSkills;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function publish(): void
    {
        if ($this->status === 'published') {
            throw new \DomainException('Role is already published.');
        }
        $this->status = 'published';
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function update(
        string $name,
        string $level,
        string $description,
        string $successCriteria,
        array $requiredSkills
    ): void {
        if ($this->status === 'published') {
            throw new \DomainException('Cannot update a published role. Create a new version instead.');
        }
        $this->name = $name;
        $this->level = $level;
        $this->description = $description;
        $this->successCriteria = $successCriteria;
        $this->requiredSkills = $requiredSkills;
    }
}
