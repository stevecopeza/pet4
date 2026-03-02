<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Application\System\Service\AdminAuditLogger;
use Pet\Domain\Support\Repository\TicketRepository;

class DeleteTicketHandler
{
    private TransactionManager $transactionManager;
    private TicketRepository $ticketRepository;
    private ?AdminAuditLogger $auditLogger;

    public function __construct(
        TransactionManager $transactionManager,
        TicketRepository $ticketRepository,
        ?AdminAuditLogger $auditLogger = null
    ) {
        $this->transactionManager = $transactionManager;
        $this->ticketRepository = $ticketRepository;
        $this->auditLogger = $auditLogger;
    }

    public function handle(DeleteTicketCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            $this->ticketRepository->delete($command->id());

            $this->auditLogger?->log('ticket_deleted', [
                'ticket_id' => $command->id(),
            ]);
        });
    }
}
