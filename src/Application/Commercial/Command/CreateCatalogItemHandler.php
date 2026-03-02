<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Commercial\Entity\CatalogItem;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;

class CreateCatalogItemHandler
{
    private TransactionManager $transactionManager;
    private CatalogItemRepository $repository;

    public function __construct(TransactionManager $transactionManager, CatalogItemRepository $repository)
    {
        $this->transactionManager = $transactionManager;
        $this->repository = $repository;
    }

    public function handle(CreateCatalogItemCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $item = new CatalogItem(
            $command->name(),
            $command->unitPrice(),
            $command->unitCost(),
            $command->type(),
            $command->sku(),
            $command->description(),
            $command->category(),
            $command->wbsTemplate()
        );

        $this->repository->save($item);
    
        });
    }
}
