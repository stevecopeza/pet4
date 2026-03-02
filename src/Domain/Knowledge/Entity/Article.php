<?php

declare(strict_types=1);

namespace Pet\Domain\Knowledge\Entity;

class Article
{
    private ?int $id;
    private string $title;
    private string $content;
    private string $category;
    private string $status;
    private ?int $malleableSchemaVersion;
    private array $malleableData;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        string $title,
        string $content,
        string $category = 'general',
        string $status = 'draft',
        ?int $id = null,
        ?int $malleableSchemaVersion = null,
        array $malleableData = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
        $this->category = $category;
        $this->status = $status;
        $this->malleableSchemaVersion = $malleableSchemaVersion;
        $this->malleableData = $malleableData;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function malleableSchemaVersion(): ?int
    {
        return $this->malleableSchemaVersion;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        string $title,
        string $content,
        string $category,
        string $status,
        array $malleableData
    ): void {
        $this->title = $title;
        $this->content = $content;
        $this->category = $category;
        $this->status = $status;
        $this->malleableData = $malleableData;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(): void
    {
        $this->status = 'archived';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
