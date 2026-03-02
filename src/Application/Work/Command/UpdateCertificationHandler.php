<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\Certification;
use Pet\Domain\Work\Repository\CertificationRepository;

class UpdateCertificationHandler
{
    private TransactionManager $transactionManager;
    private CertificationRepository $certificationRepository;

    public function __construct(TransactionManager $transactionManager, CertificationRepository $certificationRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->certificationRepository = $certificationRepository;
    }

    public function handle(UpdateCertificationCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $existing = $this->certificationRepository->findById($command->id());

        if (!$existing) {
            throw new \RuntimeException('Certification not found');
        }

        $updated = new Certification(
            $command->name(),
            $command->issuingBody(),
            $command->expiryMonths(),
            $existing->status(),
            $existing->id(),
            $existing->createdAt()
        );

        $this->certificationRepository->save($updated);
    
        });
    }
}

