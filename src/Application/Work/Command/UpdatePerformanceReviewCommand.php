<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class UpdatePerformanceReviewCommand
{
    private int $id;
    private array $content;
    private ?string $status;

    public function __construct(int $id, array $content, ?string $status = null)
    {
        $this->id = $id;
        $this->content = $content;
        $this->status = $status;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function content(): array
    {
        return $this->content;
    }

    public function status(): ?string
    {
        return $this->status;
    }
}
