<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

final class UpdateQuoteBlockCommand
{
    private int $quoteId;
    private int $blockId;
    private array $payload;

    public function __construct(int $quoteId, int $blockId, array $payload)
    {
        $this->quoteId = $quoteId;
        $this->blockId = $blockId;
        $this->payload = $payload;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function blockId(): int
    {
        return $this->blockId;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}

