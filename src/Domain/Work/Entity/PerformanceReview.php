<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class PerformanceReview
{
    private ?int $id;
    private int $employeeId;
    private int $reviewerId;
    private \DateTimeImmutable $periodStart;
    private \DateTimeImmutable $periodEnd;
    private string $status;
    private array $content;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        int $employeeId,
        int $reviewerId,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        ?int $id = null,
        string $status = 'draft',
        array $content = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->employeeId = $employeeId;
        $this->reviewerId = $reviewerId;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->id = $id;
        $this->status = $status;
        $this->content = $content;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function reviewerId(): int
    {
        return $this->reviewerId;
    }

    public function periodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function content(): array
    {
        return $this->content;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateContent(array $content): void
    {
        $this->content = $content;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function submit(): void
    {
        $this->status = 'submitted';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function finalize(): void
    {
        $this->status = 'completed';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
