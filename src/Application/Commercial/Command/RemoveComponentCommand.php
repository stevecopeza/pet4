<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class RemoveComponentCommand
{
    private int $quoteId;
    private int $componentId;

    public function __construct(
        int $quoteId,
        int $componentId
    ) {
        $this->quoteId = $quoteId;
        $this->componentId = $componentId;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function componentId(): int
    {
        return $this->componentId;
    }
}
