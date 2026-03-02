<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class RemoveCostAdjustmentCommand
{
    private int $quoteId;
    private int $adjustmentId;

    public function __construct(int $quoteId, int $adjustmentId)
    {
        $this->quoteId = $quoteId;
        $this->adjustmentId = $adjustmentId;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function adjustmentId(): int
    {
        return $this->adjustmentId;
    }
}
