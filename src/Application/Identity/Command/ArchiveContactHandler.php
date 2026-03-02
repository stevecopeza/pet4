<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Repository\ContactRepository;
use RuntimeException;

class ArchiveContactHandler
{
    private TransactionManager $transactionManager;
    private ContactRepository $contactRepository;

    public function __construct(TransactionManager $transactionManager, ContactRepository $contactRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->contactRepository = $contactRepository;
    }

    public function handle(ArchiveContactCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $contact = $this->contactRepository->findById($command->id);
        if (!$contact) {
            throw new RuntimeException("Contact not found with ID: {$command->id}");
        }

        $contact->archive();
        $this->contactRepository->save($contact);
    
        });
    }
}
