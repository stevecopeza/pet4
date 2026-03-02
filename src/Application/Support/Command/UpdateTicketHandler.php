<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Support\Repository\TicketRepository;

class UpdateTicketHandler
{
    private TransactionManager $transactionManager;
    private TicketRepository $ticketRepository;

    public function __construct(TransactionManager $transactionManager, TicketRepository $ticketRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->ticketRepository = $ticketRepository;
    }

    public function handle(UpdateTicketCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $ticket = $this->ticketRepository->findById($command->id());

        if (!$ticket) {
            throw new \RuntimeException('Ticket not found');
        }

        $ticket->update(
            $command->subject(),
            $command->description(),
            $command->priority(),
            $command->status(),
            $command->siteId(),
            $command->slaId(),
            $command->malleableData()
        );

        $this->ticketRepository->save($ticket);
    
        });
    }
}
