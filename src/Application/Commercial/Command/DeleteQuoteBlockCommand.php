<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

final class DeleteQuoteBlockCommand
{
    private int $quoteId;
    private int $blockId;

    public function __construct(int $quoteId, int $blockId)
    {
        $this->quoteId = $quoteId;
        $this->blockId = $blockId;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function blockId(): int
    {
        return $this->blockId;
    }
}

