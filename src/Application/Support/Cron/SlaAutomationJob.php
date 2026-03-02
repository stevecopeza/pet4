<?php

declare(strict_types=1);

namespace Pet\Application\Support\Cron;

use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\Service\SlaAutomationService;

class SlaAutomationJob
{
    private TicketRepository $ticketRepo;
    private SlaAutomationService $slaService;

    public function __construct(
        TicketRepository $ticketRepo,
        SlaAutomationService $slaService
    ) {
        $this->ticketRepo = $ticketRepo;
        $this->slaService = $slaService;
    }

    public function run(): void
    {
        // 1. Fetch all active tickets
        $tickets = $this->ticketRepo->findActive();

        // 2. Evaluate each ticket
        foreach ($tickets as $ticket) {
            try {
                $this->slaService->evaluate($ticket);
            } catch (\Throwable $e) {
                // Log error but continue processing other tickets
                error_log(sprintf(
                    'SLA Automation Error for Ticket ID %d: %s', 
                    $ticket->id(), 
                    $e->getMessage()
                ));
            }
        }
    }
}
