<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\Certification;
use Pet\Domain\Work\Repository\CertificationRepository;

class CreateCertificationHandler
{
    private TransactionManager $transactionManager;
    private CertificationRepository $certificationRepository;

    public function __construct(TransactionManager $transactionManager, CertificationRepository $certificationRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->certificationRepository = $certificationRepository;
    }

    public function handle(CreateCertificationCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $certification = new Certification(
            $command->name(),
            $command->issuingBody(),
            $command->expiryMonths()
        );

        $this->certificationRepository->save($certification);
    
        });
    }
}
