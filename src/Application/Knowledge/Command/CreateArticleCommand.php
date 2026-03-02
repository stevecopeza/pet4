<?php

declare(strict_types=1);

namespace Pet\Application\Knowledge\Command;

class CreateArticleCommand
{
    private string $title;
    private string $content;
    private string $category;
    private string $status;
    private array $malleableData;

    public function __construct(
        string $title,
        string $content,
        string $category = 'general',
        string $status = 'draft',
        array $malleableData = []
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->category = $category;
        $this->status = $status;
        $this->malleableData = $malleableData;
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
