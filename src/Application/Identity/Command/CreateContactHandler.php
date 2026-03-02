<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Entity\Contact;
use Pet\Domain\Identity\Entity\ContactAffiliation;
use Pet\Domain\Identity\Repository\ContactRepository;

class CreateContactHandler
{
    private TransactionManager $transactionManager;
    private ContactRepository $contactRepository;

    public function __construct(TransactionManager $transactionManager, ContactRepository $contactRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->contactRepository = $contactRepository;
    }

    public function handle(CreateContactCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $affiliations = [];
        foreach ($command->affiliations as $affData) {
            $affiliations[] = new ContactAffiliation(
                (int) $affData['customerId'],
                isset($affData['siteId']) ? (int) $affData['siteId'] : null,
                $affData['role'] ?? null,
                (bool) ($affData['isPrimary'] ?? false)
            );
        }

        $contact = new Contact(
            $command->firstName,
            $command->lastName,
            $command->email,
            $command->phone,
            $affiliations,
            null,
            null,
            $command->malleableData
        );

        $this->contactRepository->save($contact);
    
        });
    }
}
