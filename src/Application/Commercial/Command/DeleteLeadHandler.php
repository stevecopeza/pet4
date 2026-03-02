<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Repository\LeadRepository;

class DeleteLeadHandler
{
    private TransactionManager $transactionManager;
    private LeadRepository $leadRepository;

    public function __construct(TransactionManager $transactionManager, LeadRepository $leadRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->leadRepository = $leadRepository;
    }

    public function handle(DeleteLeadCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $this->leadRepository->delete($command->id());
    
        });
    }
}
