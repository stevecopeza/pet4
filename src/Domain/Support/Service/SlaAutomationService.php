<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Service;

use Pet\Application\Support\Service\SlaCheckService;
use Pet\Domain\Support\Entity\Ticket;

/**
 * @deprecated Use SlaCheckService (application layer) + SlaStateResolver (domain layer) instead.
 * Kept as a thin facade for backward compatibility during migration.
 */
class SlaAutomationService
{
    private SlaCheckService $checkService;

    public function __construct(SlaCheckService $checkService)
    {
        $this->checkService = $checkService;
    }

    /** @deprecated Use SlaCheckService::runSlaCheck() */
    public function runSlaCheck(): void
    {
        $this->checkService->runSlaCheck();
    }

    /** @deprecated Use SlaCheckService::evaluate() */
    public function evaluate(Ticket $ticket): void
    {
        $this->checkService->evaluate($ticket);
    }
}
