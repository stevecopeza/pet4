<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\Entity\Ticket;

class AssignTicketToUserHandler
{
    public function __construct(
        private TransactionManager $transactionManager,
        private TicketRepository $ticketRepository,
        private EventBus $eventBus
    ) {
    }

    public function handle(AssignTicketToUserCommand $command): Ticket
    {
        return $this->transactionManager->transactional(function () use ($command) {
            $ticket = $this->ticketRepository->findById($command->ticketId());
            if (!$ticket) {
                throw new \RuntimeException('Ticket not found');
            }

            $ticket->assignToEmployee($command->userId());
            $this->ticketRepository->save($ticket);

            foreach ($ticket->releaseEvents() as $event) {
                $this->eventBus->dispatch($event);
            }

            return $ticket;
        });
    }
}

