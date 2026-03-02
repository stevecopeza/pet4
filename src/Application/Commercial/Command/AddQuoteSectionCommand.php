<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class AddQuoteSectionCommand
{
    private int $quoteId;
    private string $name;

    public function __construct(int $quoteId, string $name)
    {
        $this->quoteId = $quoteId;
        $this->name = $name;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function name(): string
    {
        return $this->name;
    }
}

