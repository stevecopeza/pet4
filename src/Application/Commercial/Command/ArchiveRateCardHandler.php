<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Repository\RateCardRepository;

class ArchiveRateCardHandler
{
    private TransactionManager $transactionManager;
    private RateCardRepository $repository;

    public function __construct(TransactionManager $transactionManager, RateCardRepository $repository)
    {
        $this->transactionManager = $transactionManager;
        $this->repository = $repository;
    }

    public function handle(ArchiveRateCardCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            $entity = $this->repository->findById($command->id());
            if (!$entity) {
                throw new \DomainException("RateCard not found: {$command->id()}");
            }
            $entity->archive();
            $this->repository->save($entity);
        });
    }
}
