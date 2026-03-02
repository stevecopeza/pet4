<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Support\Repository\TicketRepository;

class DeleteTicketHandler
{
    private TransactionManager $transactionManager;
    private TicketRepository $ticketRepository;

    public function __construct(TransactionManager $transactionManager, TicketRepository $ticketRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->ticketRepository = $ticketRepository;
    }

    public function handle(DeleteTicketCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $this->ticketRepository->delete($command->id());
    
        });
    }
}
