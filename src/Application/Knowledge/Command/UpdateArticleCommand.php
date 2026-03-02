<?php

declare(strict_types=1);

namespace Pet\Application\Knowledge\Command;

class UpdateArticleCommand
{
    private int $id;
    private string $title;
    private string $content;
    private string $category;
    private string $status;
    private array $malleableData;

    public function __construct(
        int $id,
        string $title,
        string $content,
        string $category,
        string $status,
        array $malleableData = []
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
        $this->category = $category;
        $this->status = $status;
        $this->malleableData = $malleableData;
    }

    public function id(): int
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

    public function malleableData(): array
    {
        return $this->malleableData;
    }
}
