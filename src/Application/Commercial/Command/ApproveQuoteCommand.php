<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class ApproveQuoteCommand
{
    public function __construct(
        public readonly int $quoteId,
        public readonly int $approverUserId
    ) {}
}
