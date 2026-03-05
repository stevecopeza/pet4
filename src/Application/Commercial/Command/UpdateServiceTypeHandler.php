<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Repository\ServiceTypeRepository;

class UpdateServiceTypeHandler
{
    private TransactionManager $transactionManager;
    private ServiceTypeRepository $repository;

    public function __construct(TransactionManager $transactionManager, ServiceTypeRepository $repository)
    {
        $this->transactionManager = $transactionManager;
        $this->repository = $repository;
    }

    public function handle(UpdateServiceTypeCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            $entity = $this->repository->findById($command->id());
            if (!$entity) {
                throw new \DomainException("ServiceType not found: {$command->id()}");
            }
            $entity->update($command->name(), $command->description());
            $this->repository->save($entity);
        });
    }
}
