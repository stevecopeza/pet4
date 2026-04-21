<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class CloseOpportunityCommand
{
    public function __construct(
        private string $id,
        private string $stage // 'closed_won' | 'closed_lost'
    ) {}

    public function id(): string { return $this->id; }
    public function stage(): string { return $this->stage; }
}
