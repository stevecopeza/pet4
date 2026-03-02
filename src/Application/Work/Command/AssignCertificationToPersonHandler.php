<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\PersonCertification;
use Pet\Domain\Work\Repository\PersonCertificationRepository;

class AssignCertificationToPersonHandler
{
    private TransactionManager $transactionManager;
    private PersonCertificationRepository $personCertificationRepository;

    public function __construct(TransactionManager $transactionManager, PersonCertificationRepository $personCertificationRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->personCertificationRepository = $personCertificationRepository;
    }

    public function handle(AssignCertificationToPersonCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $personCertification = new PersonCertification(
            $command->employeeId(),
            $command->certificationId(),
            $command->obtainedDate(),
            $command->expiryDate(),
            $command->evidenceUrl()
        );

        $this->personCertificationRepository->save($personCertification);
    
        });
    }
}
