<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Repository\CatalogProductRepository;

class UpdateCatalogProductHandler
{
    private TransactionManager $transactionManager;
    private CatalogProductRepository $repository;

    public function __construct(TransactionManager $transactionManager, CatalogProductRepository $repository)
    {
        $this->transactionManager = $transactionManager;
        $this->repository = $repository;
    }

    public function handle(UpdateCatalogProductCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            $entity = $this->repository->findById($command->id());
            if (!$entity) {
                throw new \DomainException("CatalogProduct not found: {$command->id()}");
            }
            $entity->update($command->name(), $command->unitPrice(), $command->unitCost(), $command->description(), $command->category());
            $this->repository->save($entity);
        });
    }
}
