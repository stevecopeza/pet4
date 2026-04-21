<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class ConvertOpportunityToQuoteCommand
{
    public function __construct(
        private string $opportunityId,
        private int $generatedByUserId
    ) {}

    public function opportunityId(): string { return $this->opportunityId; }
    public function generatedByUserId(): int { return $this->generatedByUserId; }
}
