<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class RejectQuoteApprovalCommand
{
    public function __construct(
        public readonly int    $quoteId,
        public readonly int    $reviewerUserId,
        public readonly string $rejectionNote
    ) {}
}
