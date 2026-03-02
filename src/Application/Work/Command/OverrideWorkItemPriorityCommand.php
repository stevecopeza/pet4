<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class OverrideWorkItemPriorityCommand
{
    public function __construct(
        private string $workItemId,
        private float $overrideValue
    ) {}

    public function workItemId(): string
    {
        return $this->workItemId;
    }

    public function overrideValue(): float
    {
        return $this->overrideValue;
    }
}
