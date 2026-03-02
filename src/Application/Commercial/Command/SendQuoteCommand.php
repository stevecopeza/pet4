<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class SendQuoteCommand
{
    private int $quoteId;

    public function __construct(int $quoteId)
    {
        $this->quoteId = $quoteId;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }
}
