<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

final class QueueBillingExportForQuickBooksCommand
{
    private int $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function exportId(): int { return $this->exportId; }
}
