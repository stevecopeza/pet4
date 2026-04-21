<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class SubmitQuoteForApprovalCommand
{
    public function __construct(
        public readonly int $quoteId,
        public readonly int $submittedByUserId
    ) {}
}
