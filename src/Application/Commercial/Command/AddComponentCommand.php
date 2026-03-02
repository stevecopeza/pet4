<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class AddComponentCommand
{
    private int $quoteId;
    private string $type;
    private array $data;

    public function __construct(
        int $quoteId,
        string $type,
        array $data
    ) {
        $this->quoteId = $quoteId;
        $this->type = $type;
        $this->data = $data;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function data(): array
    {
        return $this->data;
    }
}
